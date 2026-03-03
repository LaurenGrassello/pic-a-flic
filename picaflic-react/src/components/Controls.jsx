import { useState } from "react";
import { useNavigate } from "react-router-dom";

export default function Controls({ onForYou, onDeck }) {
  const [region, setRegion] = useState("US");
  const [service, setService] = useState("");
  const navigate = useNavigate();

  const params = () => ({ region, ...(service && { service }) });
  const goChallenge = () => {
    const qs = new URLSearchParams(params()).toString();
    navigate(`/challenge${qs ? `?${qs}` : ""}`);
  };

  return (
    <div className="card">
      <div className="row">
        <br></br>
        <div
          className="homeButtons"
          style={{
            display: "flex",
            justifyContent: "space-between",
            marginBottom: "15px",
          }}
        >
          <button onClick={() => onDeck?.(params())}>Load Deck</button>
          <button onClick={() => onForYou?.(params())}>For You</button>
          <button onClick={goChallenge}>Challenge Me</button>
          <br></br>
        </div>
        <select value={region} onChange={(e) => setRegion(e.target.value)}>
          <option value="US">US</option>
          <option value="GB">GB</option>
          <option value="CA">CA</option>
        </select>
        <select value={service} onChange={(e) => setService(e.target.value)}>
          <option value="">(any service)</option>
          <option value="netflix">Netflix</option>
          <option value="prime">Prime Video</option>
          <option value="hulu">Hulu</option>
          <option value="disney">Disney+</option>
        </select>
      </div>
    </div>
  );
}
