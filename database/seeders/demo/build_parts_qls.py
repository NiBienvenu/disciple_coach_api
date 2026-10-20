#!/usr/bin/env python3
"""Adds Part 1 and Part 2 (from the brochure "Connaissez-vous les Quatre Lois
Spirituelles ?", content/QLS 11x9[1].pdf) to curriculum_seed.json and tags
every existing level as Part 3 "Faire des disciples".

Part 1 "Connecte ta vie à Jésus"      -> brochure pages 1-11
Part 2 "Bâtis ton fondement en Christ" -> brochure pages 12-17
Part 3 "Faire des disciples"           -> existing levels track_1..track_5

Run from this folder:  python3 build_parts_qls.py
Kirundi (rn) falls back to French in the seeder where no translation exists.
"""
import json
from pathlib import Path

HERE = Path(__file__).parent
SEED = HERE / "curriculum_seed.json"

PART_TITLES = {
    1: {"fr": "Connecte ta vie à Jésus", "en": "Connect your Life to Jesus"},
    2: {"fr": "Bâtis ton fondement en Christ",
        "rn": "Gutangura ubugingo bushasha muri Kristo",
        "en": "Build your foundation in Christ"},
    3: {"fr": "Faire des disciples", "en": "Make Disciples"},
}


def s(ref, text):
    return {"reference": ref, "text": text}


def lesson(track_id, n, code, title, fr, en):
    return {
        "id": f"{track_id}_lesson_{n:02d}",
        "code": code,
        "type": "main",
        "order": n,
        "title": title,
        "content": {"fr": fr, "en": en},
    }


def q(prompt, choices, correct):
    return {"prompt": prompt, "choices": choices, "correctIndex": correct}


# ---------------------------------------------------------------- Part 1 ----
CONNECT = "track_connect"
connect_lessons = [
    lesson(CONNECT, 1, "QLS1",
           {"fr": "Première loi : Dieu vous aime", "en": "First law: God loves you"},
           {
               "centralIdea": "Dieu vous aime et vous offre un plan merveilleux pour votre vie. Comme il y a des lois qui assurent l'ordre et la paix dans chaque pays, il y a des lois spirituelles, établies par Dieu, qui nous amènent à connaître la paix avec lui.",
               "objectives": [
                   "Découvrir l'amour de Dieu pour chaque personne.",
                   "Comprendre que Dieu a un plan : une vie abondante en Jésus-Christ.",
               ],
               "keyScriptures": [
                   s("Jean 3.16", "Car Dieu a tant aimé le monde qu'il a donné son Fils unique, afin que quiconque croit en lui ne périsse pas, mais qu'il ait la vie éternelle."),
                   s("Jean 10.10", "Moi, je suis venu, afin que les brebis aient la vie et qu'elles l'aient en abondance."),
               ],
               "summaryPoints": [
                   "L'amour de Dieu : il a donné son Fils unique pour nous.",
                   "Le plan de Dieu : Jésus est venu pour nous donner une vie abondante.",
                   "Pourquoi tant de gens ne connaissent-ils pas cette vie abondante ? La deuxième loi répond.",
               ],
               "reflectionQuestions": [
                   "Croyez-vous que Dieu vous aime personnellement ?",
                   "Que signifie pour vous « une vie abondante » ?",
               ],
           },
           {
               "centralIdea": "God loves you and offers a wonderful plan for your life. Just as every country has laws that keep order and peace, there are spiritual laws, set by God, that lead us to know peace with him.",
               "objectives": [
                   "Discover God's love for every person.",
                   "Understand that God has a plan: an abundant life in Jesus Christ.",
               ],
               "keyScriptures": [
                   s("John 3:16", "For God so loved the world that he gave his only Son, that whoever believes in him should not perish but have eternal life."),
                   s("John 10:10", "I came that they may have life and have it abundantly."),
               ],
               "summaryPoints": [
                   "God's love: he gave his only Son for us.",
                   "God's plan: Jesus came to give us an abundant life.",
                   "Why do so many people not know this abundant life? The second law answers.",
               ],
               "reflectionQuestions": [
                   "Do you believe God loves you personally?",
                   "What does 'an abundant life' mean to you?",
               ],
           }),
    lesson(CONNECT, 2, "QLS2",
           {"fr": "Deuxième loi : l'homme est pécheur et séparé de Dieu", "en": "Second law: we are sinful and separated from God"},
           {
               "centralIdea": "L'homme est pécheur et séparé de Dieu ; ainsi il ne peut connaître ni expérimenter l'amour et le plan de Dieu pour sa vie.",
               "objectives": [
                   "Comprendre ce que la Bible appelle le péché.",
                   "Voir que nos propres efforts ne peuvent pas nous ramener à Dieu.",
               ],
               "keyScriptures": [
                   s("Romains 3.23", "Tous ont péché et sont privés de la gloire de Dieu."),
                   s("Romains 6.23", "Car le salaire du péché, c'est la mort (la séparation spirituelle d'avec Dieu)."),
               ],
               "summaryPoints": [
                   "Dieu a créé l'homme pour vivre en relation intime avec lui.",
                   "L'homme a choisi de suivre son propre chemin : c'est le péché. Il se manifeste quand l'homme refuse de faire la volonté de Dieu.",
                   "Dieu est saint, l'homme est pécheur : une grande séparation existe entre les deux.",
                   "L'homme cherche à atteindre Dieu par ses propres moyens (vie honnête, bonne conduite, prières, religion…), mais il ne peut jamais y parvenir, ni trouver sécurité et satisfaction.",
               ],
               "reflectionQuestions": [
                   "Par quels moyens avez-vous essayé d'atteindre Dieu par vous-même ?",
                   "Pourquoi nos bonnes œuvres ne suffisent-elles pas à franchir la séparation ?",
               ],
           },
           {
               "centralIdea": "Man is sinful and separated from God, so he cannot know or experience God's love and plan for his life.",
               "objectives": [
                   "Understand what the Bible calls sin.",
                   "See that our own efforts cannot bring us back to God.",
               ],
               "keyScriptures": [
                   s("Romans 3:23", "All have sinned and fall short of the glory of God."),
                   s("Romans 6:23", "For the wages of sin is death (spiritual separation from God)."),
               ],
               "summaryPoints": [
                   "God created us to live in a close relationship with him.",
                   "We chose to go our own way: that is sin. It shows when we refuse to do God's will.",
                   "God is holy and we are sinful: a great gap separates us.",
                   "People try to reach God by their own means (an honest life, good conduct, prayers, religion…), but can never get there or find security and satisfaction.",
               ],
               "reflectionQuestions": [
                   "In what ways have you tried to reach God on your own?",
                   "Why are our good deeds not enough to cross the gap?",
               ],
           }),
    lesson(CONNECT, 3, "QLS3",
           {"fr": "Troisième loi : Jésus-Christ, la seule solution", "en": "Third law: Jesus Christ, the only solution"},
           {
               "centralIdea": "Jésus-Christ est la seule solution de Dieu pour le péché de l'homme. Par lui, vous pouvez connaître et expérimenter l'amour de Dieu et son plan pour votre vie.",
               "objectives": [
                   "Voir que Jésus est mort à notre place et qu'il est ressuscité.",
                   "Comprendre que Jésus est le seul chemin vers Dieu.",
               ],
               "keyScriptures": [
                   s("Romains 5.8", "Mais en ceci, Dieu prouve son amour envers nous : lorsque nous étions encore pécheurs, Christ est mort pour nous."),
                   s("1 Corinthiens 15.3-6", "Christ est mort pour nos péchés… il a été enseveli, il est ressuscité le troisième jour, selon les Écritures… il a été vu par Céphas, puis par les douze. Ensuite, il a été vu par plus de cinq cents frères à la fois."),
                   s("Jean 14.6", "Jésus lui dit : Moi, je suis le chemin, la vérité et la vie. Nul ne vient au Père que par moi."),
               ],
               "summaryPoints": [
                   "Il est mort à notre place.",
                   "Il est ressuscité des morts.",
                   "Il est le seul chemin : Dieu a établi le pont pour refaire la relation entre lui et l'homme, en envoyant son Fils mourir sur la croix à notre place.",
                   "Mais il ne suffit pas de connaître simplement ces trois lois…",
               ],
               "reflectionQuestions": [
                   "Que vous dit la croix sur l'amour de Dieu pour vous ?",
                   "Pourquoi la résurrection de Jésus est-elle importante ?",
               ],
           },
           {
               "centralIdea": "Jesus Christ is God's only solution for our sin. Through him you can know and experience God's love and plan for your life.",
               "objectives": [
                   "See that Jesus died in our place and rose again.",
                   "Understand that Jesus is the only way to God.",
               ],
               "keyScriptures": [
                   s("Romans 5:8", "But God shows his love for us in that while we were still sinners, Christ died for us."),
                   s("1 Corinthians 15:3-6", "Christ died for our sins… he was buried, he was raised on the third day in accordance with the Scriptures… he appeared to Cephas, then to the twelve. Then he appeared to more than five hundred brothers at one time."),
                   s("John 14:6", "Jesus said to him, I am the way, and the truth, and the life. No one comes to the Father except through me."),
               ],
               "summaryPoints": [
                   "He died in our place.",
                   "He rose from the dead.",
                   "He is the only way: God built the bridge to restore the relationship between himself and us, by sending his Son to die on the cross in our place.",
                   "But it is not enough just to know these three laws…",
               ],
               "reflectionQuestions": [
                   "What does the cross tell you about God's love for you?",
                   "Why does the resurrection of Jesus matter?",
               ],
           }),
    lesson(CONNECT, 4, "QLS4",
           {"fr": "Quatrième loi : accepter Jésus-Christ", "en": "Fourth law: receive Jesus Christ"},
           {
               "centralIdea": "Nous devons accepter personnellement Jésus-Christ comme notre Sauveur et Seigneur ; ensuite nous pouvons connaître et expérimenter l'amour et le plan de Dieu pour notre vie.",
               "objectives": [
                   "Comprendre qu'il faut recevoir Jésus personnellement, par la foi.",
                   "Distinguer « être d'accord » avec Jésus et l'accepter vraiment.",
               ],
               "keyScriptures": [
                   s("Jean 1.12", "Mais à tous ceux qui l'ont reçue, elle a donné le pouvoir de devenir enfants de Dieu, à ceux qui croient en son nom."),
                   s("Éphésiens 2.8-9", "C'est par la grâce en effet que vous êtes sauvés, par le moyen de la foi. Et cela ne vient pas de vous, c'est le don de Dieu. Ce n'est point par les œuvres, afin que personne ne se glorifie."),
                   s("Apocalypse 3.20", "Voici : je me tiens à la porte et je frappe. Si quelqu'un entend ma voix et ouvre la porte, j'entrerai chez lui, je souperai avec lui et lui avec moi."),
               ],
               "summaryPoints": [
                   "Nous devons accepter Jésus-Christ.",
                   "Nous l'acceptons par la foi ; nous faisons alors l'expérience d'une nouvelle naissance (voir Jean 3.1-8).",
                   "Nous l'acceptons en l'invitant personnellement.",
                   "Accepter Jésus, c'est se détourner de soi-même pour se tourner vers Dieu (se repentir), lui faire confiance pour qu'il entre dans notre vie et pardonne nos péchés, et le laisser faire de nous ce qu'il veut que nous soyons.",
               ],
               "reflectionQuestions": [
                   "Êtes-vous simplement d'accord avec les enseignements de Jésus, ou l'avez-vous accepté ?",
                   "Selon Apocalypse 3.20, que promet Jésus à celui qui lui ouvre la porte ?",
               ],
           },
           {
               "centralIdea": "We must personally receive Jesus Christ as Savior and Lord; then we can know and experience God's love and plan for our life.",
               "objectives": [
                   "Understand that we must receive Jesus personally, by faith.",
                   "Tell the difference between agreeing with Jesus and truly receiving him.",
               ],
               "keyScriptures": [
                   s("John 1:12", "But to all who did receive him, who believed in his name, he gave the right to become children of God."),
                   s("Ephesians 2:8-9", "For by grace you have been saved through faith. And this is not your own doing; it is the gift of God, not a result of works, so that no one may boast."),
                   s("Revelation 3:20", "Behold, I stand at the door and knock. If anyone hears my voice and opens the door, I will come in to him and eat with him, and he with me."),
               ],
               "summaryPoints": [
                   "We must receive Jesus Christ.",
                   "We receive him by faith; then we experience a new birth (see John 3:1-8).",
                   "We receive him by personal invitation.",
                   "Receiving Jesus means turning from self to God (repenting), trusting him to come into our life and forgive our sins, and letting him make us who he wants us to be.",
               ],
               "reflectionQuestions": [
                   "Do you simply agree with Jesus' teaching, or have you received him?",
                   "According to Revelation 3:20, what does Jesus promise the one who opens the door?",
               ],
           }),
    lesson(CONNECT, 5, "QLS5",
           {"fr": "Accepter Jésus-Christ par la prière", "en": "Receive Jesus Christ through prayer"},
           {
               "centralIdea": "Deux genres de vies : la vie dirigée par soi-même et la vie dirigée par Jésus-Christ. Vous pouvez maintenant accepter Jésus-Christ par la foi en priant.",
               "objectives": [
                   "Reconnaître quelle image représente le mieux votre vie.",
                   "Savoir comment inviter Jésus-Christ par une prière sincère.",
               ],
               "keyScriptures": [
                   s("Apocalypse 3.20", "Si quelqu'un entend ma voix et ouvre la porte, j'entrerai chez lui."),
               ],
               "summaryPoints": [
                   "La vie dirigée par soi-même : Jésus-Christ est en dehors de la vie, l'homme est lui-même au centre et dirige tout. Le résultat est souvent un manque d'harmonie et de la frustration.",
                   "La vie dirigée par Jésus-Christ : il est au centre, l'homme lui a cédé la place d'autorité. Il dirige les aspects de la vie, ce qui amène une harmonie avec le plan de Dieu.",
                   "Prier, c'est parler avec Dieu. Dieu regarde l'attitude de votre cœur plus que vos paroles.",
                   "Prière suggérée : « Seigneur Jésus, j'ai besoin de toi. Merci d'être mort sur la croix pour mes péchés. J'ouvre la porte de ma vie et je t'accepte comme mon Sauveur et Seigneur. Merci pour le pardon de mes péchés et pour la vie éternelle que tu me donnes. Prends la direction de ma vie. Fais de moi la personne que tu désires que je sois. »",
               ],
               "reflectionQuestions": [
                   "Quelle image représente le mieux votre vie ?",
                   "Quelle image aimeriez-vous que représente votre vie ?",
                   "Cette prière exprime-t-elle le désir de votre cœur ? Si oui, faites-la maintenant : Jésus-Christ viendra dans votre vie, comme il l'a promis.",
               ],
           },
           {
               "centralIdea": "Two kinds of lives: the self-directed life and the Christ-directed life. You can receive Jesus Christ right now by faith, through prayer.",
               "objectives": [
                   "See which picture best describes your life.",
                   "Know how to invite Jesus Christ through a sincere prayer.",
               ],
               "keyScriptures": [
                   s("Revelation 3:20", "If anyone hears my voice and opens the door, I will come in to him."),
               ],
               "summaryPoints": [
                   "The self-directed life: Jesus Christ is outside, self is at the center and runs everything. The result is often lack of harmony and frustration.",
                   "The Christ-directed life: Christ is at the center, self has handed him authority. He directs every part of life, bringing harmony with God's plan.",
                   "Prayer is talking with God. God looks at the attitude of your heart more than your words.",
                   "Suggested prayer: 'Lord Jesus, I need you. Thank you for dying on the cross for my sins. I open the door of my life and receive you as my Savior and Lord. Thank you for forgiving my sins and giving me eternal life. Take control of my life. Make me the person you want me to be.'",
               ],
               "reflectionQuestions": [
                   "Which picture best describes your life?",
                   "Which picture would you like to describe your life?",
                   "Does this prayer express the desire of your heart? If so, pray it now, and Jesus Christ will come into your life as he promised.",
               ],
           }),
]

connect_quiz = [
    q({"fr": "Selon la première loi, que vous offre Dieu ?", "en": "According to the first law, what does God offer you?"},
      [{"fr": "Des règles religieuses à suivre", "en": "Religious rules to follow"},
       {"fr": "Son amour et un plan merveilleux pour votre vie", "en": "His love and a wonderful plan for your life"},
       {"fr": "La richesse et le succès", "en": "Wealth and success"}], 1),
    q({"fr": "Qu'est-ce que le péché, selon la brochure ?", "en": "What is sin, according to the booklet?"},
      [{"fr": "Choisir son propre chemin et refuser de faire la volonté de Dieu", "en": "Choosing our own way and refusing to do God's will"},
       {"fr": "Seulement les crimes graves", "en": "Only serious crimes"},
       {"fr": "Ne pas aller à l'église", "en": "Not going to church"}], 0),
    q({"fr": "Pourquoi nos propres efforts (bonne conduite, religion…) ne suffisent-ils pas ?", "en": "Why are our own efforts (good conduct, religion…) not enough?"},
      [{"fr": "Parce que Dieu ne veut pas de nous", "en": "Because God doesn't want us"},
       {"fr": "Parce que le péché nous sépare d'un Dieu saint et qu'aucun effort humain ne franchit ce fossé", "en": "Because sin separates us from a holy God and no human effort can cross that gap"},
       {"fr": "Parce qu'il faut en faire encore plus", "en": "Because we just need to do more"}], 1),
    q({"fr": "Quelle est la seule solution de Dieu pour le péché de l'homme ?", "en": "What is God's only solution for our sin?"},
      [{"fr": "Jésus-Christ, mort à notre place et ressuscité", "en": "Jesus Christ, who died in our place and rose again"},
       {"fr": "Une vie honnête", "en": "An honest life"},
       {"fr": "Les prières seules", "en": "Prayers alone"}], 0),
    q({"fr": "Comment acceptons-nous Jésus-Christ ?", "en": "How do we receive Jesus Christ?"},
      [{"fr": "En naissant dans une famille chrétienne", "en": "By being born into a Christian family"},
       {"fr": "En étant simplement d'accord avec ses enseignements", "en": "By simply agreeing with his teaching"},
       {"fr": "Par la foi, en l'invitant personnellement dans notre vie", "en": "By faith, inviting him personally into our life"}], 2),
]

# ---------------------------------------------------------------- Part 2 ----
FOUND = "track_foundation"
foundation_lessons = [
    lesson(FOUND, 1, "QLS6",
           {"fr": "Comment savoir que Jésus-Christ est dans votre vie", "rn": "Ni gute womenya ko Yesu Kristo ari mu bugingo bwawe", "en": "How to know Christ is in your life"},
           {
               "centralIdea": "Si vous avez accepté Jésus-Christ, il est dans votre vie, car il l'a promis. Votre assurance repose sur la fidélité de Dieu lui-même et sur sa Parole.",
               "objectives": [
                   "Savoir où se trouve Jésus-Christ par rapport à vous, selon Apocalypse 3.20.",
                   "Fonder l'assurance du salut sur la promesse de Dieu.",
               ],
               "keyScriptures": [
                   s("Apocalypse 3.20", "Si quelqu'un entend ma voix et ouvre la porte, j'entrerai chez lui."),
                   s("1 Jean 5.11-13", "Et voici ce témoignage : Dieu nous a donné la vie éternelle et cette vie est en son Fils. Celui qui a le Fils a la vie ; celui qui n'a pas le Fils de Dieu n'a pas la vie. Cela, je vous l'ai écrit, afin que vous sachiez que vous avez la vie éternelle, vous qui croyez au nom du Fils de Dieu."),
               ],
               "summaryPoints": [
                   "Jésus-Christ a dit qu'il entrerait dans votre vie. Il ne vous trompe pas.",
                   "Vous savez que Dieu a exaucé votre prière par la fidélité de Dieu lui-même et par sa Parole.",
                   "La Bible promet la vie éternelle à tous ceux qui acceptent Jésus-Christ : vous l'avez dès le moment où vous l'avez accepté.",
                   "Remerciez Dieu de ce que Jésus-Christ est dans votre vie et que vous avez la vie éternelle.",
               ],
               "reflectionQuestions": [
                   "Avez-vous accepté Jésus-Christ dans votre vie ?",
                   "Selon Apocalypse 3.20, où est Jésus-Christ maintenant par rapport à vous ?",
                   "Sur quelle base savez-vous que Dieu a exaucé votre prière ?",
               ],
           },
           {
               "centralIdea": "If you have received Jesus Christ, he is in your life, because he promised it. Your assurance rests on God's own faithfulness and on his Word.",
               "objectives": [
                   "Know where Jesus Christ is in relation to you, according to Revelation 3:20.",
                   "Base the assurance of salvation on God's promise.",
               ],
               "keyScriptures": [
                   s("Revelation 3:20", "If anyone hears my voice and opens the door, I will come in to him."),
                   s("1 John 5:11-13", "And this is the testimony, that God gave us eternal life, and this life is in his Son. Whoever has the Son has life; whoever does not have the Son of God does not have life. I write these things to you who believe in the name of the Son of God, that you may know that you have eternal life."),
               ],
               "summaryPoints": [
                   "Jesus Christ said he would come into your life. He does not deceive you.",
                   "You know God answered your prayer because of God's own faithfulness and his Word.",
                   "The Bible promises eternal life to all who receive Jesus Christ: you have it from the moment you received him.",
                   "Thank God that Jesus Christ is in your life and that you have eternal life.",
               ],
               "reflectionQuestions": [
                   "Have you received Jesus Christ into your life?",
                   "According to Revelation 3:20, where is Jesus Christ right now in relation to you?",
                   "On what basis do you know God answered your prayer?",
               ],
           }),
    lesson(FOUND, 2, "QLS7",
           {"fr": "Ne dépendez pas de vos sentiments", "en": "Do not depend on your feelings"},
           {
               "centralIdea": "L'autorité suprême du chrétien est la Parole de Dieu (la Bible) et non ses sentiments. Le chrétien vit par la foi, c'est-à-dire la confiance en la fidélité de Dieu et de sa Parole.",
               "objectives": [
                   "Comprendre que les sentiments changent selon les circonstances.",
                   "Apprendre à remercier Dieu en croyant à sa promesse.",
               ],
               "keyScriptures": [
                   s("Hébreux 13.5", "Dieu lui-même a dit : je ne te délaisserai pas ni ne t'abandonnerai."),
               ],
               "summaryPoints": [
                   "Aujourd'hui vous vous sentez peut-être heureux, demain triste : les sentiments varient selon les circonstances.",
                   "Ne comptez pas sur vos sentiments pour avoir l'assurance que Jésus-Christ est toujours dans votre vie.",
                   "Puisqu'il ne vous trompe pas, remerciez Dieu de ce que, dans les bons comme dans les mauvais jours, il reste présent dans votre vie.",
                   "En disant « Merci », vous montrez que vous croyez à sa promesse.",
               ],
               "reflectionQuestions": [
                   "Vos sentiments vous ont-ils déjà fait douter de la présence de Dieu ?",
                   "Quelle promesse de Dieu pouvez-vous opposer à ces doutes ?",
               ],
           },
           {
               "centralIdea": "The Christian's final authority is God's Word (the Bible), not feelings. A Christian lives by faith, that is, trust in the faithfulness of God and his Word.",
               "objectives": [
                   "Understand that feelings change with circumstances.",
                   "Learn to thank God by believing his promise.",
               ],
               "keyScriptures": [
                   s("Hebrews 13:5", "God himself has said, I will never leave you nor forsake you."),
               ],
               "summaryPoints": [
                   "Today you may feel happy, tomorrow sad: feelings change with circumstances.",
                   "Do not rely on feelings for assurance that Jesus Christ is still in your life.",
                   "Since he does not deceive you, thank God that in good days and bad days he stays present in your life.",
                   "By saying 'Thank you', you show that you believe his promise.",
               ],
               "reflectionQuestions": [
                   "Have your feelings ever made you doubt God's presence?",
                   "Which promise of God can you set against those doubts?",
               ],
           }),
    lesson(FOUND, 3, "QLS8",
           {"fr": "Ce qui arrive lorsque vous acceptez Jésus-Christ", "en": "What happens when you receive Christ"},
           {
               "centralIdea": "Dès le moment où vous avez accepté Jésus-Christ par la foi, en engageant votre volonté, de nombreuses choses sont arrivées dans votre vie.",
               "objectives": [
                   "Connaître cinq choses qui arrivent quand on accepte Jésus-Christ.",
                   "Remercier Dieu pour ce qu'il a fait.",
               ],
               "keyScriptures": [
                   s("Colossiens 1.27", "Christ en vous, l'espérance de la gloire."),
                   s("Jean 5.24", "Celui qui écoute ma parole et qui croit à celui qui m'a envoyé a la vie éternelle."),
                   s("2 Corinthiens 5.17", "Si quelqu'un est en Christ, il est une nouvelle créature."),
               ],
               "summaryPoints": [
                   "Jésus-Christ est venu dans votre vie (Apocalypse 3.20 ; Colossiens 1.27).",
                   "Vos péchés ont été pardonnés (Colossiens 1.14).",
                   "Vous êtes devenu enfant de Dieu (Jean 1.12).",
                   "Vous avez reçu la vie éternelle (Jean 5.24).",
                   "Vous avez commencé la vie passionnante pour laquelle Dieu vous a créé (Jean 10.10 ; 2 Corinthiens 5.17).",
               ],
               "reflectionQuestions": [
                   "Pouvez-vous imaginer quelque chose de plus merveilleux que d'accepter Jésus-Christ ?",
                   "Voudriez-vous remercier Dieu en priant maintenant pour ce qu'il a fait pour vous ?",
               ],
           },
           {
               "centralIdea": "The moment you received Jesus Christ by faith, as an act of your will, many things happened in your life.",
               "objectives": [
                   "Know five things that happen when you receive Jesus Christ.",
                   "Thank God for what he has done.",
               ],
               "keyScriptures": [
                   s("Colossians 1:27", "Christ in you, the hope of glory."),
                   s("John 5:24", "Whoever hears my word and believes him who sent me has eternal life."),
                   s("2 Corinthians 5:17", "If anyone is in Christ, he is a new creation."),
               ],
               "summaryPoints": [
                   "Christ came into your life (Revelation 3:20; Colossians 1:27).",
                   "Your sins were forgiven (Colossians 1:14).",
                   "You became a child of God (John 1:12).",
                   "You received eternal life (John 5:24).",
                   "You began the exciting life God created you for (John 10:10; 2 Corinthians 5:17).",
               ],
               "reflectionQuestions": [
                   "Can you imagine anything more wonderful than receiving Jesus Christ?",
                   "Would you like to thank God in prayer right now for what he has done for you?",
               ],
           }),
    lesson(FOUND, 4, "QLS9",
           {"fr": "Suggestions pour la croissance chrétienne", "en": "Suggestions for Christian growth"},
           {
               "centralIdea": "La croissance spirituelle est le résultat d'une confiance constante en Jésus-Christ. Une vie de foi vous permettra de faire confiance de plus en plus à Dieu pour chaque détail de votre vie.",
               "objectives": [
                   "Connaître six pratiques qui font grandir la vie chrétienne.",
                   "Choisir une pratique à commencer dès aujourd'hui.",
               ],
               "keyScriptures": [
                   s("Hébreux 10.38", "Et mon juste vivra par la foi."),
               ],
               "summaryPoints": [
                   "Parlez avec Dieu chaque jour par la prière (Jean 15.7).",
                   "Lisez la Bible chaque jour (Actes 17.11) ; commencez par l'Évangile de Jean.",
                   "Obéissez à Dieu moment après moment (Jean 14.21).",
                   "Témoignez de Jésus-Christ par votre vie et par vos paroles (Matthieu 4.19 ; Jean 15.8).",
                   "Faites confiance à Dieu pour chaque détail de votre vie (1 Pierre 5.7).",
                   "Laissez le Saint-Esprit diriger votre vie quotidienne et votre témoignage et les rendre puissants (Galates 5.16-17 ; Actes 1.8).",
               ],
               "reflectionQuestions": [
                   "Laquelle de ces six pratiques est la plus faible dans votre vie ?",
                   "Quand allez-vous prier et lire la Bible chaque jour ?",
               ],
           },
           {
               "centralIdea": "Spiritual growth comes from constant trust in Jesus Christ. A life of faith lets you trust God more and more with every detail of your life.",
               "objectives": [
                   "Know six practices that grow the Christian life.",
                   "Choose one practice to start today.",
               ],
               "keyScriptures": [
                   s("Hebrews 10:38", "My righteous one shall live by faith."),
               ],
               "summaryPoints": [
                   "Talk with God every day in prayer (John 15:7).",
                   "Read the Bible every day (Acts 17:11); start with the Gospel of John.",
                   "Obey God moment by moment (John 14:21).",
                   "Witness for Christ by your life and your words (Matthew 4:19; John 15:8).",
                   "Trust God with every detail of your life (1 Peter 5:7).",
                   "Let the Holy Spirit lead and empower your daily life and witness (Galatians 5:16-17; Acts 1:8).",
               ],
               "reflectionQuestions": [
                   "Which of these six practices is weakest in your life?",
                   "When will you pray and read the Bible each day?",
               ],
           }),
    lesson(FOUND, 5, "QLS10",
           {"fr": "L'importance de la communion chrétienne", "en": "The importance of Christian fellowship"},
           {
               "centralIdea": "Quand des morceaux de bois sont ensemble dans un feu, ils brûlent ardemment ; séparés, leur feu s'éteint. Il en est de même de vos relations avec d'autres chrétiens.",
               "objectives": [
                   "Comprendre pourquoi la communion avec d'autres chrétiens est essentielle.",
                   "S'engager à fréquenter une église dès cette semaine.",
               ],
               "keyScriptures": [
                   s("Hébreux 10.25", "N'abandonnons pas notre assemblée, comme c'est la coutume de quelques-uns, mais exhortons-nous mutuellement, et cela d'autant plus que vous voyez le Jour s'approcher."),
               ],
               "summaryPoints": [
                   "Rencontrez d'autres personnes qui ont accepté Jésus-Christ comme Seigneur et Sauveur.",
                   "Fréquentez régulièrement une église où l'on croit que la Bible est la vérité et où Jésus-Christ est adoré.",
                   "Commencez cette semaine.",
                   "Si cette brochure vous a aidé, lisez-la à quelqu'un d'autre.",
               ],
               "reflectionQuestions": [
                   "Avec quels chrétiens êtes-vous en communion aujourd'hui ?",
                   "Quelle église allez-vous fréquenter cette semaine ?",
                   "À qui pourriez-vous lire cette brochure ?",
               ],
           },
           {
               "centralIdea": "When pieces of wood are together in a fire, they burn brightly; separated, their fire dies out. The same is true of your relationships with other Christians.",
               "objectives": [
                   "Understand why fellowship with other Christians is essential.",
                   "Commit to attending a church this week.",
               ],
               "keyScriptures": [
                   s("Hebrews 10:25", "Not neglecting to meet together, as is the habit of some, but encouraging one another, and all the more as you see the Day drawing near."),
               ],
               "summaryPoints": [
                   "Meet other people who have received Jesus Christ as Lord and Savior.",
                   "Attend a church regularly where the Bible is believed to be true and Jesus Christ is worshiped.",
                   "Start this week.",
                   "If this booklet helped you, read it to someone else.",
               ],
               "reflectionQuestions": [
                   "Which Christians are you in fellowship with today?",
                   "Which church will you attend this week?",
                   "Who could you read this booklet to?",
               ],
           }),
]

foundation_quiz = [
    q({"fr": "Selon Apocalypse 3.20, où est Jésus-Christ si vous l'avez accepté ?", "en": "According to Revelation 3:20, where is Jesus Christ if you have received him?"},
      [{"fr": "Dans votre vie, comme il l'a promis", "en": "In your life, as he promised"},
       {"fr": "Seulement à l'église", "en": "Only at church"},
       {"fr": "On ne peut pas le savoir", "en": "You cannot know"}], 0),
    q({"fr": "Sur quoi repose l'assurance que Christ est dans votre vie ?", "en": "What is the assurance that Christ is in your life based on?"},
      [{"fr": "Sur nos sentiments", "en": "Our feelings"},
       {"fr": "Sur la fidélité de Dieu et sa Parole", "en": "God's faithfulness and his Word"},
       {"fr": "Sur l'avis des autres", "en": "Other people's opinion"}], 1),
    q({"fr": "Laquelle de ces choses arrive quand vous acceptez Jésus-Christ ?", "en": "Which of these happens when you receive Jesus Christ?"},
      [{"fr": "Vous devenez enfant de Dieu et recevez la vie éternelle", "en": "You become a child of God and receive eternal life"},
       {"fr": "Vous n'aurez plus jamais de difficultés", "en": "You will never have problems again"},
       {"fr": "Rien ne change", "en": "Nothing changes"}], 0),
    q({"fr": "La croissance spirituelle est le résultat de…", "en": "Spiritual growth is the result of…"},
      [{"fr": "Une confiance constante en Jésus-Christ", "en": "Constant trust in Jesus Christ"},
       {"fr": "Des efforts sans Dieu", "en": "Effort without God"},
       {"fr": "Une émotion forte", "en": "A strong emotion"}], 0),
    q({"fr": "Que nous apprend l'image des morceaux de bois dans le feu ?", "en": "What does the picture of logs in a fire teach us?"},
      [{"fr": "Qu'il vaut mieux vivre sa foi seul", "en": "That faith is better lived alone"},
       {"fr": "Que nous avons besoin de la communion avec d'autres chrétiens", "en": "That we need fellowship with other Christians"},
       {"fr": "Qu'il faut éviter l'église", "en": "That we should avoid church"}], 1),
]


def main():
    data = json.loads(SEED.read_text(encoding="utf-8"))
    tracks = [t for t in data["tracks"] if t["id"] not in (CONNECT, FOUND)]

    connect = {
        "id": CONNECT,
        "title": {"fr": "Connecte ta vie à Jésus", "en": "Connect your Life to Jesus"},
        "description": {
            "fr": "Les quatre lois spirituelles : comment connaître l'amour de Dieu et recevoir Jésus-Christ comme Sauveur et Seigneur.",
            "en": "The four spiritual laws: how to know God's love and receive Jesus Christ as Savior and Lord.",
        },
        "order": 1, "part": 1, "partTitle": PART_TITLES[1], "previousTrackId": None,
        "lessons": connect_lessons,
        "quiz": {"questions": [dict(qq, id=f"{CONNECT}_q{i+1}") for i, qq in enumerate(connect_quiz)]},
    }
    foundation = {
        "id": FOUND,
        "title": {"fr": "Bâtis ton fondement en Christ", "rn": PART_TITLES[2]["rn"], "en": "Build your foundation in Christ"},
        "description": {
            "fr": "L'assurance du salut, la vie par la foi plutôt que par les sentiments, la croissance chrétienne et la communion fraternelle.",
            "en": "Assurance of salvation, living by faith rather than feelings, Christian growth and fellowship.",
        },
        "order": 2, "part": 2, "partTitle": PART_TITLES[2], "previousTrackId": CONNECT,
        "lessons": foundation_lessons,
        "quiz": {"questions": [dict(qq, id=f"{FOUND}_q{i+1}") for i, qq in enumerate(foundation_quiz)]},
    }

    tracks.sort(key=lambda t: t["order"])
    for i, t in enumerate(tracks):
        t["order"] = i + 3
        t["part"] = 3
        t["partTitle"] = PART_TITLES[3]
    # Part 3 starts once Part 2 is approved by the coach.
    if tracks:
        tracks[0]["previousTrackId"] = FOUND

    data["tracks"] = [connect, foundation] + tracks
    SEED.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    for t in data["tracks"]:
        print(t["order"], t["part"], t["id"], len(t["lessons"]), "lessons")


if __name__ == "__main__":
    main()
