#!/usr/bin/env python3
"""Details from 14 organisations' own websites (read 18 Oct 2026) -> their listings. The organisation's own website is
the newest source, so its values replace older ones ("newer": import_listings.php logs each old value). Only what the
site states; personal and parliament emails, paid-service and media numbers, and another body's contacts are left out.
Types and services are added only.
Usage: python3 build_sites.py > data/out/sites.rows.json
"""
import json
import sys

P = lambda number, label="", whatsapp=False: {"number": number, "label": label, "whatsapp": whatsapp}  # noqa: E731

ROWS = [
    dict(post_id=9263, phones=[P("+91 97240 00939")], email="rrsafoundation@gmail.com", website="https://www.rrsaindia.org/",
         address="Aashiyana for Animals, near Jol Village Water Canal, Bakrol - Vadtal Road, Jol Village, Anand, Gujarat 388315", founded="2016",
         instagram="https://www.instagram.com/rrsaindia/", youtube="https://youtube.com/@rrsaindia", donate_url="https://www.rrsaindia.org/donate-now",
         volunteer_url="https://www.rrsaindia.org/help-us", types=["animal-shelter", "veterinary-hospital", "animal-ambulance", "emergency-rescue"],
         services=["rescue", "treatment", "shelter", "ambulance", "sterilisation", "vaccination", "adoption", "feeding"]),
    dict(post_id=9051, phones=[P("9829843726", "Helpline (Udaipur): ill or injured street animals, cruelty"), P("9602325253", "Helpline (Udaipur)"), P("9784005989", "Helpline (Udaipur)")],
         email="info@animalaidunlimited.org", address="Badi Village, Liyo Ka Gurha, near Badi TB Hospital, Udaipur, Rajasthan 313011",
         instagram="https://www.instagram.com/animalaid_india/", youtube="https://www.youtube.com/animalaidunlimited",
         donate_url="https://www.animalaidunlimited.org/donation/", types=["animal-shelter", "sanctuary", "veterinary-hospital", "animal-ambulance", "rescue-group"],
         services=["rescue", "treatment", "sterilisation", "vaccination", "adoption", "ambulance", "shelter", "cattle-care"]),
    dict(post_id=9549, phones=[P("011-20818191", "Head office"), P("011-20818194", "Head office")], founded="1994",
         types=["rescue-group"], services=["rescue", "shelter", "ambulance", "sterilisation", "treatment", "adoption", "boarding-foster", "legal-help"]),
    dict(post_id=9114, phones=[P("+91 9845425678", "Trauma & Rescue Centre: report an injured animal"), P("080-22947301", "Trauma & Rescue Centre"),
                               P("+91 9108855888", "Small Animal Specialty Hospital (pets, paid)")],
         email="cupablr@cupaindia.org", address="Kensington Apartments, Flat A-3, First Floor, 18/1 Ulsoor Cross Road, off Bazaar Street, Ulsoor, Bengaluru 560008",
         founded="1991", types=["veterinary-hospital", "animal-shelter", "rescue-group"],
         services=["rescue", "treatment", "shelter", "adoption", "sterilisation", "vaccination", "ambulance"]),
    dict(post_id=9262, phones=[P("+91 91725 11100", "24-hour helpline, Pune: wildlife and large domestic animals"), P("+91 73877 71050", "24-hour helpline, Baramati"),
                               P("+91 99223 30286", "24-hour helpline, Roha"), P("+91 86981 12211", "24-hour helpline, Nashik"), P("+91 98909 99111", "Office (11 am–5 pm)")],
         email="info@resqct.org", address="RESQ Centre, Plot No. 3906, Paud Mulshi Road, Old Jakat Naka, near 115 Hilltown, Bavdhan, Pune 411021",
         founded="2007", types=["wildlife-rescue", "sanctuary", "emergency-rescue"], services=["rescue", "wildlife-rescue", "treatment", "ambulance", "shelter", "cattle-care"]),
    dict(post_id=9260, phones=[P("9958866067", "WhatsApp message only: injured dog or cat in South Delhi", True)], email="redpawsrescue@gmail.com",
         instagram="http://www.instagram.com/redpaws_rescue", x="http://www.twitter.com/redpawsrescue", donate_url="https://www.redpawsrescue.com/donate",
         volunteer_url="https://www.redpawsrescue.com/how-you-can-help", services=["sterilisation", "adoption", "boarding-foster", "treatment", "feeding", "vaccination", "rescue"]),
    dict(post_id=1764, phones=[P("+91-11-46601096", "Head office, New Delhi"), P("+91-9871963535", "Wildlife helpline, Delhi NCT"), P("+91-9917109666", "Wildlife helpline, Agra"),
                               P("+91-9825011117", "Wildlife helpline, Vadodara"), P("+91-7006692300", "Wildlife helpline, Srinagar"), P("+91-9419778280", "Wildlife helpline, Srinagar")],
         email="info@wildlifesos.org", address="D-210, Defence Colony, New Delhi 110024", founded="1995", registration="S-32542",
         types=["wildlife-rescue", "sanctuary", "rescue-group"], services=["rescue", "wildlife-rescue", "treatment", "shelter", "ambulance"]),
    dict(post_id=9274, phones=[P("+91 9560802425", "Animal helpline and ambulance (24x7)"), P("+91 8882325407", "Animal helpline and ambulance (24x7)")],
         types=["veterinary-hospital", "animal-shelter", "gaushala"], services=["treatment", "ambulance", "sterilisation", "vaccination", "shelter", "adoption", "cattle-care", "rescue"]),
    dict(post_id=9158, phones=[P("022-24170017", "Registered office, Dadar"), P("+91 9320056581", "Deonar Centre helpline (9 am–4 pm)"),
                               P("+91 9320056585", "Turbhe (Navi Mumbai) Centre helpline, night service"), P("+91 9320056589", "Panvel Centre helpline"),
                               P("+91 9320056588", "Dog and cat sterilisation appointments")],
         email="info@idaindia.org", address="7, Shanti Kunj, 124, Hindu Colony, 5th Lane, Dadar, Mumbai 400014", founded="1996",
         x="https://twitter.com/IDA_India", types=["animal-shelter", "rescue-group"],
         services=["sterilisation", "treatment", "vaccination", "rescue", "ambulance", "adoption", "boarding-foster"]),
    dict(post_id=9538, phones=[P("+91 9035999372", "Rescue helpline"), P("+91 8123038270", "Rescue helpline")], email="charliescareblr@gmail.com",
         address="Survey No. 124, Mitteganahalli Cross, Kogilu Village, Yelahanka Hobli, Bengaluru North 560064", founded="2013",
         instagram="https://www.instagram.com/charliesanimalrescuecentre", x="https://twitter.com/careshelterblr", youtube="https://www.youtube.com/@charliecare2822",
         donate_url="https://www.charlies-care.com/donate", volunteer_url="https://www.charlies-care.com/volunteers-form",
         types=["animal-shelter", "animal-ambulance"], services=["rescue", "ambulance", "treatment", "shelter", "adoption", "vaccination"]),
    dict(post_id=1192, phones=[P("+91 8591659398", "Hospital, 24 hours (emergency)"), P("+91 9372787750", "Main office (9:30 am–4:30 pm)"),
                               P("+91 8779690135", "Adoption (9:30 am–4:30 pm)"), P("+91 8369882992", "Animal Birth Control (9:30 am–4:30 pm)"), P("022-24135434", "")],
         email="bsdpha.bspca@gmail.com", address="Bai Sakarbai Dinshaw Petit Hospital for Animals, Dr. S. S. Rao Road, Parel, Mumbai 400012",
         types=["veterinary-hospital", "animal-ambulance"], services=["treatment", "ambulance", "sterilisation", "adoption", "cattle-care"]),
    dict(post_id=3024, phones=[P("+91 99629 98886", "WhatsApp", True)], email="admin@bluecrossofindia.org", founded="1964",
         instagram="https://www.instagram.com/blue_cross_rescues/", x="https://twitter.com/bluecross_", youtube="https://www.youtube.com/channel/UC-ormTG2Ir9Im8fjddQsYOg",
         types=["veterinary-hospital", "rescue-group"], services=["rescue", "treatment", "sterilisation", "vaccination", "ambulance"]),
    dict(post_id=233, phones=[P("+91-8913296217", "Main office"), P("+91-7330844751", "Shelter")], email="info@vspca.org",
         address="26-15-200, Main Road, Visakhapatnam, Andhra Pradesh 530001", instagram="https://www.instagram.com/vspca_vizag/", x="https://twitter.com/VisakhaSPCA",
         types=["animal-shelter", "rescue-group"], services=["rescue", "ambulance", "sterilisation", "shelter", "adoption", "cattle-care"]),
    dict(post_id=16445, phones=[P("+91 7993912345", "Phone and WhatsApp", True)], address="Moulali, Hyderabad 500040, Telangana", founded="2017",
         facebook="https://www.facebook.com/awbptrust", instagram="https://www.instagram.com/awbptrust/", x="https://twitter.com/awbptrust",
         youtube="https://www.youtube.com/c/awbptrust", donate_url="https://awbptrust.org/donate", volunteer_url="https://awbptrust.org/volunteer", services=["feeding"]),
]

if __name__ == "__main__":
    json.dump([dict(r, newer=True, types=r.get("types", [])) for r in ROWS], sys.stdout, ensure_ascii=False, indent=1)
    print(f"{len(ROWS)} listings", file=sys.stderr)
