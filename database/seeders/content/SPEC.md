# Lesson content format (Gnowbe-style pages)

Each lesson is one JSON file: `content/<level_slug>/<CODE>.json`.
`content/levels.json` lists the levels and the lesson order.

## Lesson file

```json
{
  "code": "AFF1",
  "order": 1,
  "type": "main",
  "title": { "fr": "...", "en": "...", "rn": "..." },
  "pages": [
    {
      "image": "bible-open",
      "video_url": null,
      "title": { "fr": "...", "en": "...", "rn": "..." },
      "body":  { "fr": "...", "en": "...", "rn": "..." }
    }
  ],
  "quiz": [
    {
      "prompt":  { "fr": "...", "en": "...", "rn": "..." },
      "choices": [ { "fr": "...", "en": "...", "rn": "..." }, ... 3 or 4 choices ],
      "correctIndex": 0,
      "explanation": { "fr": "...", "en": "...", "rn": "..." }
    }
  ]
}
```

- `type`: `main` or `supplementary` (codes with `S` after the level digit, e.g. `2S03`, are supplementary).
- `image`: a key from the image list below, or `null`. Put an image on roughly every second page
  (always on the first page). Pick the key whose picture matches the page.
- `video_url`: leave `null` unless the source names a specific public video link.
- Every text field has all three languages: `fr` (French), `en` (English), `rn` (Kirundi).

## Pages

A lesson has **6 to 12 short pages**. One idea per page. A learner should read a page in under a minute
on a phone (about 40 to 120 words). Page title: 2 to 7 words.

Suggested flow (adapt to the source):
1. Intro page: what this lesson is about, why it matters (this replaces "central idea").
2. "Ce que vous allez apprendre" / "What you will learn" / "Ivyo uza kwiga": the objectives as a bullet list.
3. Teaching pages, one point each, with the key Bible verses quoted.
4. Optional "Pour aller plus loin" application page (practical steps).
5. "En résumé" / "In summary" / "Mu ncamake": 3 to 5 bullets.
6. "Réfléchissez" / "Reflect" / "Zirikana": 2 to 4 personal reflection questions.

Do NOT copy worksheet blanks (`____`), slide numbers, copyright lines, or office addresses.
Turn fill-in-the-blank study questions into clear teaching: state the answer the verse gives.

## Body text format (small Markdown subset, nothing else)

- Paragraphs separated by one blank line (`\n\n`).
- `**bold**` for key words (use little).
- Bullet list: lines starting with `- `.
- Numbered list: lines starting with `1. `, `2. ` ...
- Bible quote: lines starting with `> `. The last line of the quote is the reference, starting with `> — `
  (em dash allowed only here), e.g.
  ```
  > Car Dieu a tant aimé le monde qu'il a donné son Fils unique...
  > — Jean 3 : 16
  ```
  Use the Bible version the source uses. French: Segond (Colombe / NEG) wording; English: NIV/ESV-like wording;
  Kirundi: Bibiliya Yera wording when the Kirundi source gives it; otherwise translate faithfully.
  Kirundi book names: Yohana, Abaroma, Abefeso, Abaheburayo, Ivyakozwe n'Intumwa, Matayo, Luka, Mariko,
  1 Abakorinto, 2 Abakorinto, Abagalatiya, Abafilipi, Abakolosayi, 1 Yohana, Ivyahishuriwe, Zaburi, Imigani.

## Quiz (end of each lesson)

3 to 5 multiple-choice questions per lesson (Part 1 lessons: 5). 3 or 4 choices each, exactly one correct.
Test understanding of the lesson, not trivia. Vary `correctIndex`. `explanation` is one short sentence.

## Language and tone

- Simple words (about 6th grade level), warm, direct ("vous" in French, "you", "wewe" in Kirundi).
- Faithful to the source teaching. Do not add new doctrine.
- Use the Kirundi source text when it exists. When it does not, translate from French carefully.
- No emdash characters anywhere except the `> — ` Bible reference marker.

## Image keys

| key | picture |
|---|---|
| god-love | big heart with warm light rays from above (God's love) |
| gods-plan | winding path toward a bright sunrise (God's plan, abundant life) |
| sin-separation | person on one cliff, sun/God on the other, deep gap between |
| human-effort | small figures building ladders and arrows that fall short across a gap |
| cross-bridge | a cross forming a bridge across the gap |
| empty-tomb | open tomb with stone rolled away, sunrise |
| door-knock | a door with light behind it, a hand knocking (Rev 3:20) |
| two-circles | two circles: self on the throne vs Christ on the throne (dots = areas of life) |
| prayer | hands folded in prayer with soft light |
| bible-open | open Bible with light |
| assurance | anchor or shield with a check mark (assurance / certainty) |
| fact-faith-feeling | small train: engine "Fact", coal car "Faith", caboose "Feeling" (no words needed, use icons) |
| new-life | green sprout coming out of soil |
| growth-tree | young tree growing tall with roots |
| fire-logs | campfire with logs together, one log alone and dark |
| church-community | group of diverse people together under one roof |
| holy-spirit | dove with soft flame/light |
| breathing | lungs/air waves (spiritual breathing: confess and receive) |
| walking-path | person walking on a path with footprints |
| witness | two people talking, speech bubble with a small cross |
| testimony | person with before/after arrow (old life to new life) |
| great-commission | globe with people and arrows going out to the world |
| multiplication | network: 1 person to 2 to 4 to 8 (dots and lines) |
| discipleship-chain | chain of 4 people passing a light (2 Tim 2:2) |
| small-group | circle of 5 or 6 people sitting with Bibles |
| mentor | two people, one guiding the other with hand on shoulder |
| teaching | teacher at a board with learners |
| stewardship | open hands holding seed / coins and a small plant |
| grace-gift | wrapped gift box with light |
| identity | person looking at a mirror that reflects a crown (child of God) |
| god-majesty | mountains under a vast starry sky (attributes of God) |
| eternity | hourglass and a horizon (eternal perspective) |
| gods-will | compass and an open road |
| movement | many small people forming a wave / flowing toward a city |
| servant-leader | basin and towel (Jesus washing feet) |
| training | workshop table with people practicing in pairs |
| strategy | map with pins and a route |
| worship | raised hands under light (glorify God) |
| love-faith | heart held in open hands |
| planning | calendar and checklist |
| conversation | group discussion with speech bubbles |
| obedience | person kneeling, path ahead lit |
