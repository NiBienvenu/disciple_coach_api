import json
import os

SEED_DIR = os.path.dirname(os.path.abspath(__file__))
TRACK_ORDER = ["track_1", "track_2", "track_3", "track_4", "track_5"]
PREVIOUS = {
    "track_1": None,
    "track_2": "track_1",
    "track_3": "track_2",
    "track_4": "track_3",
    "track_5": "track_4",
}

tracks = []
for i, track_id in enumerate(TRACK_ORDER, start=1):
    with open(os.path.join(SEED_DIR, f"{track_id}.json"), encoding="utf-8") as f:
        data = json.load(f)
    assert data["trackId"] == track_id, f"trackId mismatch in {track_id}.json: {data['trackId']}"
    tracks.append({
        "id": track_id,
        "title": data["title"],
        "description": data["description"],
        "order": i,
        "previousTrackId": PREVIOUS[track_id],
        "lessons": data["lessons"],
        "quiz": data["quiz"],
    })

out_path = os.path.join(SEED_DIR, "curriculum_seed.json")
with open(out_path, "w", encoding="utf-8") as f:
    json.dump({"tracks": tracks}, f, ensure_ascii=False, indent=2)

total_lessons = sum(len(t["lessons"]) for t in tracks)
total_questions = sum(len(t["quiz"]["questions"]) for t in tracks)
print(f"Wrote {out_path}")
print(f"{len(tracks)} tracks, {total_lessons} lessons, {total_questions} quiz questions")
