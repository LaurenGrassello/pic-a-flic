<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MessageController
{
    public function __construct(private EntityManagerInterface $em)
    {}

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** GET /messages — inbox messages for current user */
    public function index(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();

        // Get latest message per conversation (grouped by the other user)
        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.subject, m.body, m.read_at, m.created_at,
                    u.id AS sender_id, u.display_name AS sender_name,
                    CASE
                        WHEN m.sender_id = ? THEN m.recipient_id
                        ELSE m.sender_id
                    END AS other_user_id
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.recipient_id = ? OR m.sender_id = ?
             ORDER BY m.created_at DESC",
            [$meId, $meId, $meId]
        );

        // Deduplicate — keep only the latest message per conversation
        $seen = [];
        $conversations = [];
        foreach ($rows as $row) {
            $otherId = (int) $row['other_user_id'];
            if (!isset($seen[$otherId])) {
                $seen[$otherId] = true;
                $conversations[] = $row;
            }
        }

        // Mark received messages as read
        $conn->executeStatement(
            "UPDATE messages SET read_at = NOW()
             WHERE recipient_id = ? AND read_at IS NULL",
            [$meId]
        );

        return $this->json($res, ['results' => $conversations]);
    }

    /** GET /messages/thread/{userId} — full thread between me and another user */
    public function thread(Request $req, Response $res, array $args): Response
    {
        $meId = (int) $req->getAttribute('uid');
        $otherId = (int) ($args['userId'] ?? 0);

        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        if ($otherId <= 0) {
            return $this->json($res, ['error' => 'Invalid user'], 422);
        }

        $conn = $this->em->getConnection();

        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.subject, m.body, m.read_at, m.created_at,
                    sender.id AS sender_id, sender.display_name AS sender_name,
                    recipient.id AS recipient_id, recipient.display_name AS recipient_name
             FROM messages m
             JOIN users sender ON sender.id = m.sender_id
             JOIN users recipient ON recipient.id = m.recipient_id
             WHERE (m.sender_id = ? AND m.recipient_id = ?)
                OR (m.sender_id = ? AND m.recipient_id = ?)
             ORDER BY m.created_at ASC",
            [$meId, $otherId, $otherId, $meId]
        );

        // Mark as read
        $conn->executeStatement(
            "UPDATE messages SET read_at = NOW()
             WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL",
            [$meId, $otherId]
        );

        return $this->json($res, ['results' => $rows]);
    }

    /** GET /messages/unread-count */
    public function unreadCount(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        $count = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM messages
             WHERE recipient_id = ? AND read_at IS NULL",
            [$meId]
        );

        return $this->json($res, ['count' => $count]);
    }

    /** POST /messages  { recipient_id, subject, body } */
    public function send(Request $req, Response $res): Response
    {
        $meId = (int) $req->getAttribute('uid');
        if ($meId <= 0) {
            return $this->json($res, ['error' => 'Unauthorized'], 401);
        }

        $data = json_decode((string) $req->getBody(), true) ?: [];
        $recipientId = (int) ($data['recipient_id'] ?? 0);
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($recipientId <= 0 || $subject === '' || $body === '') {
            return $this->json($res, ['error' => 'recipient_id, subject and body are required'], 422);
        }

        if ($recipientId === $meId) {
            return $this->json($res, ['error' => 'Cannot message yourself'], 422);
        }

        // Verify accepted friendship
        $conn = $this->em->getConnection();
        $friendship = $conn->fetchOne(
            "SELECT id FROM friendships
             WHERE ((requester_id = ? AND addressee_id = ?)
                OR (requester_id = ? AND addressee_id = ?))
             AND status = 'accepted'",
            [$meId, $recipientId, $recipientId, $meId]
        );

        if (!$friendship) {
            return $this->json($res, ['error' => 'You can only message accepted friends'], 403);
        }

        $conn->insert('messages', [
            'sender_id' => $meId,
            'recipient_id' => $recipientId,
            'subject' => $subject,
            'body' => $body,
        ]);

        return $this->json($res, ['ok' => true, 'id' => (int) $conn->lastInsertId()], 201);
    }
}