#!/usr/bin/env python3
"""Rebuild affermissement_level1.json + merge into track_1 / curriculum_seed from PPTX.

Usage (from repo root):
  python3 disciple_coach_api/database/seeders/demo/build_affermissement_level1.py
"""
from __future__ import annotations

import json
import re
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
CONTENT = ROOT / "content" / "Affermissements"
DEMO = Path(__file__).resolve().parent
QLS_SRC = ROOT / "content" / "QLS 11x9[1].pdf"
QLS_DEST = ROOT / "disciple_coach_api" / "storage" / "app" / "public" / "curriculum" / "qls.pdf"

FILES = {
    "fr": {
        1: CONTENT / "Aff Fra" / "Afferm 1 en powerpoint.pptx",
        2: CONTENT / "Aff Fra" / "Afferm 2 en PP.pptx",
        3: CONTENT / "Aff Fra" / "Affer 3 en PP.pptm.pptx",
        4: CONTENT / "Aff Fra" / "Afferm 4 .pptm.pptx",
        5: CONTENT / "Aff Fra" / "Afferm5 en PP.pptx",
        6: CONTENT / "Aff Fra" / "Aff 6 en PP.pptx",
    },
    "en": {
        1: CONTENT / "Aff ENG" / "Afferm 1 ENG.pptx",
        2: CONTENT / "Aff ENG" / "Afferm 2 en PP Engl.pptx",
        3: CONTENT / "Aff ENG" / "Affer 3a Engl.pptx",
        4: CONTENT / "Aff ENG" / "Afferm 4a ENG.ppt",
        5: CONTENT / "Aff ENG" / "Afferm5 en PP ENG.pptx",
        6: CONTENT / "Aff ENG" / "Aff 6 en PP ENG.pptx",
    },
    "rn": {
        1: CONTENT / "AFF KI" / "Afferm 1 en pp Kdi.pptx",
        2: CONTENT / "AFF KI" / "Afferm 2 en PP Kdi.pptx",
        3: CONTENT / "AFF KI" / "Affer 3 en PP Kdi.pptm.pptx",
        4: CONTENT / "AFF KI" / "Affermissement 4 Kdi.pptx",
        5: CONTENT / "AFF KI" / "Afferm5 en PP Kdi.pptx",
        6: CONTENT / "AFF KI" / "Aff 6 en PP Kdi.pptx",
    },
}

TITLES = {
    1: {
        "fr": "Comment commencer votre vie nouvelle en Jésus-Christ",
        "en": "How to begin your new life in Jesus Christ",
        "rn": "Uko wotangura ubugingo busha muri Yesu Kristo",
    },
    2: {
        "fr": "Comment commencer votre vie nouvelle de communion avec Jésus-Christ",
        "en": "How to begin your new life of fellowship with Jesus Christ",
        "rn": "Uko wotangura ubugingo busha bwo gusangira na Yesu Kristo",
    },
    3: {
        "fr": "Comment commencer votre vie nouvelle dans le Saint-Esprit",
        "en": "How to begin your new life in the Holy Spirit",
        "rn": "Uko wotangura ubugingo busha muri Mpwemu Yera",
    },
    4: {
        "fr": "Comment commencer votre vie nouvelle de croissance en Jésus-Christ",
        "en": "How to begin your new life of growth in Jesus Christ",
        "rn": "Uko wotangura ubugingo busha bwo gukura muri Yesu Kristo",
    },
    5: {
        "fr": "Comment commencer votre vie nouvelle dans la Parole de Dieu",
        "en": "How to begin your new life in the Word of God",
        "rn": "Uko wotangura ubugingo busha mu Ijambo ry'Imana",
    },
    6: {
        "fr": "Comment commencer votre vie nouvelle dans la prière",
        "en": "How to begin your new life in prayer",
        "rn": "Uko wotangura ubugingo busha mu gusenga",
    },
}


def extract_slides(path: Path) -> list[str]:
    if not path.exists() or path.suffix.lower() not in {".pptx", ".pptm"}:
        return []
    with zipfile.ZipFile(path) as z:
        slides = sorted(
            [n for n in z.namelist() if re.match(r"ppt/slides/slide\d+\.xml$", n)],
            key=lambda n: int(re.search(r"(\d+)", n).group(1)),
        )
        out: list[str] = []
        for s in slides:
            root = ET.fromstring(z.read(s))
            texts = [
                t.text.strip()
                for t in root.iter("{http://schemas.openxmlformats.org/drawingml/2006/main}t")
                if t.text and t.text.strip()
            ]
            out.append(re.sub(r"\s+", " ", " ".join(texts)).strip())
        return out


def build_content(slides: list[str], lang: str, n: int) -> dict:
    if not slides and lang == "en" and n == 4:
        return {
            "centralIdea": (
                "After receiving Christ you are born into God's family with everything needed "
                "for an abundant Christian life. Spiritual growth comes through prayer, Bible study, and obedience."
            ),
            "objectives": [
                "Practice prayer as conversation with God (Philippians 4:6-7).",
                "Feed on Scripture for spiritual growth.",
                "Walk in obedience as a mark of love for Christ.",
            ],
            "keyScriptures": [
                {
                    "reference": "Philippians 4:6-7",
                    "text": "Do not be anxious about anything, but in everything by prayer and petition, with thanksgiving, present your requests to God.",
                }
            ],
            "summaryPoints": [
                "Receiving Christ starts a brand-new life.",
                "Growth rests on prayer, the Word, and obedience.",
                "God already gave what you need to grow.",
            ],
            "reflectionQuestions": [
                "How is your prayer life today?",
                "How regularly do you read Scripture?",
                "Where is God inviting fresh obedience?",
            ],
        }

    central = ""
    for idx in [4, 5, 6, 3]:
        if idx < len(slides) and len(slides[idx]) > 60:
            central = slides[idx][:500]
            break
    if not central and slides:
        central = slides[min(1, len(slides) - 1)][:500]

    summary = [s[:280] for s in slides[4:] if len(s) >= 40 and s.count("_") <= 8][:5]
    reflections = []
    for s in slides:
        if "?" in s and 20 <= len(s) <= 220 and s.count("_") <= 5:
            reflections.append(s)
        if len(reflections) >= 4:
            break

    return {
        "centralIdea": central,
        "objectives": summary[:3] or [central[:120]],
        "keyScriptures": [],
        "summaryPoints": summary,
        "reflectionQuestions": reflections
        or [
            "What stood out to you in this lesson?"
            if lang == "en"
            else (
                "Qu’est-ce qui vous a marqué dans cette leçon ?"
                if lang == "fr"
                else "Ni iki cawe gushimisha muri iyi somo?"
            )
        ],
    }


def main() -> None:
    raw: dict = {}
    for lang, nums in FILES.items():
        raw[lang] = {}
        for n, p in nums.items():
            slides = extract_slides(p)
            raw[lang][n] = {"file": p.name, "slides": slides, "slide_count": len(slides)}

    (DEMO / "affermissement_raw.json").write_text(
        json.dumps(raw, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    # Reuse already-authored quiz from existing level1 if present
    existing = {}
    level1_path = DEMO / "affermissement_level1.json"
    if level1_path.exists():
        existing = json.loads(level1_path.read_text(encoding="utf-8"))

    lessons = []
    for n in range(1, 7):
        lesson = {
            "id": f"track_1_lesson_{n:02d}",
            "code": f"1A{n:02d}",
            "type": "main",
            "order": n,
            "title": TITLES[n],
            "content": {
                lang: build_content(raw[lang][n]["slides"], lang, n) for lang in ("fr", "en", "rn")
            },
        }
        if n == 1:
            lesson["resource_url"] = "/storage/curriculum/qls.pdf"
        lessons.append(lesson)

    level1 = {
        "id": "track_1",
        "title": {
            "fr": "Niveau 1 : Fondation",
            "rn": "Urwego rwa 1 : Ishingiro",
            "en": "Level 1: Foundation",
        },
        "description": {
            "fr": "Les Affermissements 1–6 : commencer votre vie nouvelle en Jésus-Christ. Ressource : Quatre Lois Spirituelles (QLS).",
            "en": "Affermissements 1–6: begin your new life in Jesus Christ. Resource: Four Spiritual Laws (QLS).",
            "rn": "Affermissements 1–6: gutangura ubugingo busha muri Yesu Kristo. Igikoresho: Amategeko ane y'Impwemu (QLS).",
        },
        "order": 1,
        "previousTrackId": None,
        "lessons": lessons,
        "quiz": existing.get("quiz")
        or {"questions": []},
    }

    level1_path.write_text(json.dumps(level1, ensure_ascii=False, indent=2), encoding="utf-8")
    (DEMO / "track_1.json").write_text(json.dumps(level1, ensure_ascii=False, indent=2), encoding="utf-8")

    tracks = []
    for i in range(1, 6):
        tracks.append(json.loads((DEMO / f"track_{i}.json").read_text(encoding="utf-8")))
    (DEMO / "curriculum_seed.json").write_text(
        json.dumps({"tracks": tracks}, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    if QLS_SRC.exists():
        QLS_DEST.parent.mkdir(parents=True, exist_ok=True)
        QLS_DEST.write_bytes(QLS_SRC.read_bytes())

    print("Built Affermissement L1 + merged curriculum_seed.json")


if __name__ == "__main__":
    main()
