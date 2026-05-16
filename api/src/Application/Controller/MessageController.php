<?php
declare (strict_types = 1);

namespace PicaFlic\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PicaFlic\Domain\Entity\User;
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
        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.subject, m.body, m.read_at, m.created_at,
                    u.id AS sender_id, u.display_name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.recipient_id = ?
             ORDER BY m.created_at DESC",
            [$meId]
        );

        // Mark all as read
        $conn->executeStatement(
            "UPDATE messages SET read_at = NOW()
             WHERE recipient_id = ? AND read_at IS NULL",
            [$meId]
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