#!/usr/bin/env python3
"""Draft guides and templates (Knowledge Centre + Resources), October 2026. Written as DRAFTS for owner approval.

Usage: python3 drafts-2026-10.py > drafts.json, then on the server:
  wp eval-file aw_deploy/tools/content/create_drafts.php drafts.json
Facts are limited to the Acts, Rules and documents held in the Research Library, each linked.
"""
import html
import json

R = "/research/"
LAW = {
    "pca": R + "the-prevention-of-cruelty-to-animals-act-1960-as-amended-in-1982/",
    "abc": R + "animal-birth-control-rules-2023/",
    "petshop": R + "the-prevention-of-cruelty-to-animals-pet-shop-rules-2018/",
    "breeding": R + "the-prevention-of-cruelty-to-animals-dog-breeding-and-marketing-rules-2017/",
    "transport": R + "the-transport-of-animals-rules-1978/",
    "performing": R + "performing-animals-registration-rules-2001/",
    "sc2025": R + "supreme-court-judgment-of-22-august-2025-on-community-dogs-2025-insc-1018/",
    "rabies": R + "rabies-who-fact-sheet/",
}
NOTE = ("This guide is general information, not legal advice. Laws and court orders change; check "
        "<a href=\"/legal-watch/\">Legal Watch</a> and the original documents.")


def p(t):
    return f"<!-- wp:paragraph -->\n<p>{t}</p>\n<!-- /wp:paragraph -->\n"


def h(t):
    return f"<!-- wp:heading {{\"level\":2}} -->\n<h2 class=\"wp-block-heading\">{t}</h2>\n<!-- /wp:heading -->\n"


def ul(items):
    li = "".join(f"<!-- wp:list-item -->\n<li>{i}</li>\n<!-- /wp:list-item -->\n" for i in items)
    return f"<!-- wp:list -->\n<ul class=\"wp-block-list\">{li}</ul>\n<!-- /wp:list -->\n"


def note(t=NOTE):
    return f"<!-- wp:paragraph {{\"fontSize\":\"small\"}} -->\n<p class=\"has-small-font-size\"><em>{t}</em></p>\n<!-- /wp:paragraph -->\n"


def pre(t):
    return f"<!-- wp:preformatted -->\n<pre class=\"wp-block-preformatted\">{html.escape(t)}</pre>\n<!-- /wp:preformatted -->\n"


def a(key, text):
    return f"<a href=\"{LAW[key]}\">{text}</a>"


POSTS = [
    {
        "type": "aw_guide", "slug": "animal-welfare-laws-in-india-explained",
        "title": "Animal welfare laws in India, in plain words",
        "excerpt": "What the main animal welfare laws say, who enforces them, and where to read the originals.",
        "categories": ["laws-rules", "cruelty-prosecution"],
        "content": p("A short map of the laws that protect animals in India. Each heading links to the full text in our Research Library.")
        + h("The Constitution")
        + ul(["Article 51A(g) makes it a fundamental duty of every citizen to have compassion for living creatures."])
        + h("Prevention of Cruelty to Animals Act, 1960")
        + ul([f"The main animal welfare law. {a('pca', 'Read the Act')}.",
              "Section 11 lists acts of cruelty, including beating, overloading, abandoning an animal, and keeping it without enough food, water or shelter.",
              "The Act set up the Animal Welfare Board of India (AWBI), which advises governments and recognises animal welfare organisations.",
              "Fines under Section 11 are very low (Rs 10 to Rs 50 for a first offence), which is why serious cases are also filed under the criminal law below."])
        + h("Bharatiya Nyaya Sanhita, 2023 (criminal law)")
        + ul(["Section 325 makes killing, poisoning, maiming or disabling any animal an offence, punishable with up to five years in prison, a fine, or both. It replaced Sections 428 and 429 of the old Indian Penal Code.",
              "These offences can be reported to the police. See <a href=\"/knowledge/how-to-report-animal-cruelty/\">How to report animal cruelty</a>."])
        + h("Rules under the PCA Act")
        + ul([f"{a('abc', 'Animal Birth Control Rules, 2023')}: community dogs are sterilised, vaccinated and returned to the same area. They also set out feeding arrangements for resident welfare associations.",
              f"{a('petshop', 'Pet Shop Rules, 2018')} and {a('breeding', 'Dog Breeding and Marketing Rules, 2017')}: pet shops and breeders must register with the State Animal Welfare Board.",
              f"{a('transport', 'Transport of Animals Rules, 1978')}: space, food, water and rest for animals in transit.",
              f"{a('performing', 'Performing Animals (Registration) Rules, 2001')}: animals used in films and performances must be registered."])
        + h("Courts")
        + ul([f"Court orders shape how these laws apply. Read the {a('sc2025', 'Supreme Court judgment of 22 August 2025 on community dogs')} and follow new orders on <a href=\"/legal-watch/\">Legal Watch</a>."])
        + h("Wild animals and birds")
        + ul(["Wild animals and many native birds, such as parakeets and munias, are protected under the Wildlife (Protection) Act, 1972. They cannot be caught, kept or sold. Report captive wildlife to the state forest department."])
        + note(),
    },
    {
        "type": "aw_guide", "slug": "adopting-a-dog-or-cat",
        "title": "Adopting a dog or cat: a practical guide",
        "excerpt": "How to adopt responsibly: where to find animals, preparing your home, first vet visit and the first weeks.",
        "categories": ["adoption", "pets-breeding"],
        "content": p("Adopting gives a rescued animal a home and frees space for another in need. Plan for a commitment of 10 to 15 years.")
        + h("Before you adopt")
        + ul(["Check that everyone at home agrees, and that your housing allows pets.",
              "Think about time, space and cost: food, vet care, vaccinations and care when you travel.",
              "Consider an Indian (indie) dog or cat. They are hardy, adapt well and are the animals most often waiting for homes."])
        + h("Where to adopt")
        + ul(["Shelters and rescue groups in the <a href=\"/directory/\">directory</a>, and adoption days listed under <a href=\"/opportunities/\">Opportunities</a>.",
              f"If you buy instead, use only a pet shop or breeder registered with the State Animal Welfare Board ({a('petshop', 'Pet Shop Rules')}, {a('breeding', 'Dog Breeding Rules')})."])
        + h("The first weeks")
        + ul(["Visit a vet in the first week for a health check, deworming and vaccinations, including anti-rabies.",
              "Sterilise your pet when the vet advises. It prevents unwanted litters and some illnesses.",
              "Give a new animal a quiet corner, a routine and patience. Settling in can take a few weeks.",
              "Keep the adoption agreement and vaccination records safe."])
        + h("If it doesn't work out")
        + ul(["Never abandon an animal; abandonment is cruelty under the Prevention of Cruelty to Animals Act. Contact the shelter or rescuer you adopted from."])
        + note(),
    },
    {
        "type": "aw_guide", "slug": "feeding-community-animals-good-practice",
        "title": "Feeding community animals: good practice",
        "excerpt": "Where, when and what to feed community dogs, cats, cattle and birds, and how to avoid conflicts.",
        "categories": ["feeders-caregivers", "community-dogs"],
        "content": p("Regular, clean feeding keeps community animals healthy and calm, and makes sterilisation and vaccination easier.")
        + h("Where and when")
        + ul([f"Use a fixed spot and time, away from entrances, children's play areas and busy roads. The {a('abc', 'Animal Birth Control Rules, 2023')} ask resident welfare associations to arrange designated feeding spots.",
              f"Follow court orders on feeding; see the {a('sc2025', 'Supreme Court judgment of 22 August 2025')} and <a href=\"/legal-watch/\">Legal Watch</a>.",
              "Clean up after every feed so leftovers do not attract other animals or cause complaints."])
        + h("What to feed")
        + ul(["Dogs and cats: plain cooked rice or roti with curd, eggs or meat, or commercial pet food. Keep fresh water out.",
              "Avoid chocolate, grapes and raisins, onions, garlic, heavily spiced or salty food, and cooked bones.",
              "Cattle: fresh fodder and vegetable peels. Never feed food in plastic bags; swallowed plastic kills cattle.",
              "Birds: clean water in shallow bowls, especially in summer; grains rather than bread."])
        + h("Health")
        + ul(["Note any animal that is injured, limping or not eating, and see <a href=\"/knowledge/found-an-injured-animal-what-to-do/\">Found an injured animal?</a>",
              "Help get the animals you feed sterilised and vaccinated through your municipal ABC programme or a group in the <a href=\"/directory/\">directory</a>."])
        + note(),
    },
    {
        "type": "aw_guide", "slug": "living-alongside-animals",
        "title": "Living alongside animals: avoiding and solving conflicts",
        "excerpt": "Practical ways to prevent conflicts with community dogs, monkeys, snakes and birds, and what to do after a bite.",
        "categories": ["community-dogs", "wildlife", "rabies-public-health"],
        "content": p("Most conflicts with animals come from fear, food or crowding, and can be prevented.")
        + h("Community dogs")
        + ul(["Walk calmly, don't run, and avoid staring or startling a dog that is eating or sleeping.",
              f"Sterilised and vaccinated dogs are calmer and less territorial. Removing dogs from an area is not allowed under the {a('abc', 'ABC Rules, 2023')}; sterilisation is the lawful way to reduce numbers.",
              "Settle feeding or noise problems through your residents' association and local caregivers."])
        + h("If you are bitten or scratched")
        + ul([f"Wash the wound with soap and running water for at least 15 minutes, then go to a hospital the same day for anti-rabies treatment. Rabies is fatal once symptoms start, and fully preventable with prompt treatment ({a('rabies', 'WHO fact sheet')})."])
        + h("Monkeys, snakes and other wildlife")
        + ul(["Don't feed monkeys; feeding makes them bold and dependent on people.",
              "Keep away from snakes and call the state forest department or a wildlife rescue group. Most snakes are harmless, and all are protected under the Wildlife (Protection) Act, 1972.",
              "If a bird or wild animal is injured, keep it in a quiet, ventilated box and call a rescue group from the <a href=\"/directory/\">directory</a>."])
        + note(),
    },
    {
        "type": "aw_guide", "slug": "animal-welfare-faqs",
        "title": "Animal welfare: frequently asked questions",
        "excerpt": "Short answers to common questions about community dogs, cruelty, pets and wildlife, with links to the law.",
        "categories": ["community-dogs", "laws-rules"],
        "content": h("Can community dogs be removed from my area?")
        + p(f"No. Under the {a('abc', 'Animal Birth Control Rules, 2023')}, dogs are sterilised, vaccinated and returned to the same area.")
        + h("Is it legal to feed community dogs?")
        + p(f"Yes, at designated spots. The ABC Rules ask resident welfare associations to arrange feeding areas, and the {a('sc2025', 'Supreme Court judgment of 22 August 2025')} addresses feeding. Check <a href=\"/legal-watch/\">Legal Watch</a> for later orders.")
        + h("Who do I call for an injured animal?")
        + p("See <a href=\"/helplines/\">Helplines</a>. In many states, 1962 sends a mobile veterinary unit. Local groups are in the <a href=\"/directory/\">directory</a>.")
        + h("How do I report cruelty?")
        + p("Follow <a href=\"/knowledge/how-to-report-animal-cruelty/\">How to report animal cruelty</a>, and use the <a href=\"/resources/\">complaint template</a>.")
        + h("Can I keep a parakeet or munia?")
        + p("No. Native wild birds are protected under the Wildlife (Protection) Act, 1972, and cannot be kept or sold.")
        + h("Where can I buy a pet legally?")
        + p(f"Only from a pet shop or breeder registered with the State Animal Welfare Board ({a('petshop', 'Pet Shop Rules')}, {a('breeding', 'Dog Breeding Rules')}). Better still, <a href=\"/knowledge/adopting-a-dog-or-cat/\">adopt</a>.")
        + note(),
    },
    {
        "type": "aw_resource", "slug": "animal-cruelty-police-complaint-template",
        "title": "Police complaint for animal cruelty (template)",
        "excerpt": "A fill-in template for reporting animal cruelty to the police under the BNS and the PCA Act.",
        "categories": ["cruelty-prosecution", "laws-rules"],
        "content": p("Copy this, fill in the details in square brackets, sign it, and submit it at the police station that covers the place of the incident. Keep a copy with the receiving stamp, or ask for the FIR number.")
        + pre("""To,
The Station House Officer,
[Police station name, address]

Date: [DD/MM/YYYY]

Subject: Complaint of cruelty to an animal under Section 325 of the Bharatiya Nyaya Sanhita, 2023 and Section 11 of the Prevention of Cruelty to Animals Act, 1960

Sir/Madam,

I, [your name], [age], residing at [your address], wish to report the following:

1. On [date], at about [time], at [exact place], [name or description of the person, if known] [describe what happened: e.g. beat / poisoned / injured / abandoned] a [species, colour, description of the animal].
2. The animal is now [alive and injured / dead / missing]. [Name of vet or rescue group, if any] has [treated / examined] it.
3. Witnesses: [names and phone numbers, if any].
4. Evidence attached: [photos / videos / vet report / post-mortem report].

These acts are offences under Section 325 of the Bharatiya Nyaya Sanhita, 2023 and Section 11 of the Prevention of Cruelty to Animals Act, 1960. I request you to register an FIR and take action against the person responsible.

Yours faithfully,
[Signature]
[Name, phone number]""")
        + note("This template is general information, not legal advice. For serious cases, ask a lawyer or an animal welfare organisation to help."),
    },
    {
        "type": "aw_resource", "slug": "rti-template-abc-programme",
        "title": "RTI application: your city's ABC programme (template)",
        "excerpt": "An RTI template asking your municipal body for data on its Animal Birth Control and anti-rabies programme.",
        "categories": ["animal-birth-control", "campaigns-civic-action"],
        "content": p("Use this Right to Information (RTI) application to ask your municipal corporation or council how its Animal Birth Control (ABC) programme is run. Send it by post with the fee, or file it online if your state has an RTI portal.")
        + pre("""To,
The Public Information Officer,
[Municipal corporation / council name]
[Address]

Application under Section 6(1) of the Right to Information Act, 2005

1. Name of applicant: [your name]
2. Address: [your address]
3. Information requested, for the period [from date] to [to date]:
   a) Number of dogs sterilised and vaccinated against rabies under the Animal Birth Control programme, month by month.
   b) Names of the agencies or organisations carrying out the programme, and copies of their agreements.
   c) Amount budgeted and spent on the programme.
   d) Location of each ABC centre, and the number of dogs it can hold.
   e) Copies of the monitoring committee's meeting minutes for the programme.
4. Fee: Rs 10 paid by [postal order / court fee stamp / online payment, details].
5. I am / am not a Below Poverty Line card holder.

Date: [DD/MM/YYYY]
Signature: [your signature]""")
        + note("The information must be given within 30 days. If it isn't, you can file a first appeal with the First Appellate Authority of the same office."),
    },
]

json.dump(POSTS, __import__("sys").stdout, ensure_ascii=False)
