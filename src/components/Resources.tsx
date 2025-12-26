import { ExternalLink, Globe } from "lucide-react";
import { useEffect } from "react";
import { motion } from "motion/react";
import Slider from "react-slick";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function Resources() {
  // Inject Slick Carousel CSS
  useEffect(() => {
    const link1 = document.createElement("link");
    link1.rel = "stylesheet";
    link1.href = "https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css";
    document.head.appendChild(link1);

    const link2 = document.createElement("link");
    link2.rel = "stylesheet";
    link2.href = "https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css";
    document.head.appendChild(link2);
    
    // Inject custom CSS to hide scrollbars and customize dots
    const style = document.createElement("style");
    style.innerHTML = `
      .media-carousel-wrapper {
        overflow: hidden !important;
      }
      .media-carousel-wrapper .slick-list {
        overflow: hidden !important;
      }
      .media-carousel-wrapper .slick-track {
        display: flex !important;
      }
      .media-carousel-wrapper .slick-slide {
        height: auto;
      }
      .media-carousel-wrapper .slick-slide > div {
        height: 100%;
      }
      .media-carousel-wrapper .slick-dots {
        bottom: -40px;
      }
      .media-carousel-wrapper .slick-dots li button:before {
        color: var(--muted-foreground);
        opacity: 0.3;
        font-size: 8px;
      }
      .media-carousel-wrapper .slick-dots li.slick-active button:before {
        color: var(--foreground);
        opacity: 1;
      }
    `;
    document.head.appendChild(style);

    return () => {
      document.head.removeChild(link1);
      document.head.removeChild(link2);
      document.head.removeChild(style);
    };
  }, []);

  const mediaCoverage = [
    {
      title: "Death sentence to animals: Thousands write to Supreme Court",
      source: "INDIA TV NEWS",
      link: "https://www.indiatvnews.com/news/india/death-sentence-to-animals-thousands-write-to-supreme-court-over-november-7-order-on-stray-dogs-2025-11-29-1019576",
      image: "https://resize.indiatvnews.com/en/resize/newbucket/1200_-/2025/11/stray-dogs-1764431206.webp"
    },
    {
      title: "Nationwide Letter Campaign Urges SC To Strengthen Animal Rights",
      source: "OUTLOOK INDIA",
      link: "https://www.outlookindia.com/national/nationwide-letter-campaign-urges-supreme-court-to-strengthen-animal-rights",
      image: "https://www.thestatesman.com/wp-content/uploads/2023/10/IMG-20231008-WA0131-1-1.jpg"
    },
    {
      title: "Nationwide campaign urges citizens to write to SC for animal welfare",
      source: "DECCAN HERALD",
      link: "https://www.deccanherald.com/india/nationwide-campaign-urges-citizens-to-write-to-sc-for-animal-welfare-over-10000-letters-sent-3814221",
      image: "https://media.assettype.com/deccanherald%2F2025-07-03%2Fxtwf03w2%2Ffile81ckurb8pmae15z6gk6.jpeg?w=900&auto=format%2Ccompress&fit=max"
    },
    {
      title: "Animal activists to participate in letter campaign",
      source: "TIMES OF INDIA",
      link: "https://timesofindia.indiatimes.com/city/mangaluru/animal-activists-to-participate-in-letter-campaign/articleshow/125618323.cms",
      image: "https://static.toiimg.com/thumb/msid-125366856,imgsize-71822,width-400,height-225,resizemode-72/animal-lovers-and-activists-in-mumbai-protest-supreme-court-order-on-sheltering-stray-dogs.jpg"
    },
    {
      title: "Animal lovers spam Supreme Court with letter petitions",
      source: "THE NEW INDIAN EXPRESS",
      link: "https://www.newindianexpress.com/cities/delhi/2025/Nov/30/animal-lovers-spam-supreme-court-with-letter-petitions-urge-rollback-of-stray-dog-removal-order",
      image: "https://media.newindianexpress.com/newindianexpress%2F2025-11-29%2Fwqn8p91t%2FAnimal-lovers-spam.jpg?rect=0%2C0%2C576%2C324&w=1200&auto=format%2Ccompress&fit=max"
    },
    {
      title: "Over 1,00,000 people filed letter petition in Supreme Court",
      source: "INDIA TV",
      link: "https://www.indiatv.in/india/national/over-100000-people-filed-letter-petition-in-supreme-court-appealing-not-to-remove-stray-dogs-from-streets-2025-11-29-1179585",
      image: "https://static.toiimg.com/thumb/msid-125366856,imgsize-71822,width-400,height-225,resizemode-72/animal-lovers-and-activists-in-mumbai-protest-supreme-court-order-on-sheltering-stray-dogs.jpg"
    },
    {
      title: "Over 1.5L from TN write to SC seeking recall of dog removal order",
      source: "DT NEXT",
      link: "https://www.dtnext.in/news/tamilnadu/over-15l-from-tn-write-to-sc-seeking-recall-of-dog-removal-order-855059",
      image: "https://resize.indiatv.in/resize/newbucket/1200_675/2025/11/dogs-1764432422.webp"
    },
  ];

  const sliderSettings = {
    dots: true,
    infinite: true,
    speed: 500,
    slidesToShow: 3,
    slidesToScroll: 1,
    autoplay: true,
    autoplaySpeed: 3000,
    pauseOnHover: true,
    arrows: false,
    responsive: [
      {
        breakpoint: 1024,
        settings: {
          slidesToShow: 2,
        }
      },
      {
        breakpoint: 640,
        settings: {
          slidesToShow: 1,
        }
      }
    ]
  };

  return (
    <section 
      className="py-16 md:py-24 bg-background" 
      id="resources"
      aria-labelledby="resources-heading"
    >
      <div className="container mx-auto px-4">
        <div className="mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <motion.div
              className="inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius-full)] mb-4"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <Globe className="w-4 h-4 text-foreground" aria-hidden="true" />
              <span className="text-foreground" style={{ fontSize: '14px', fontWeight: 'var(--font-weight-regular)' }}>In The News</span>
            </motion.div>
            <h2 id="resources-heading" className="text-foreground mb-3">
              Media Coverage
            </h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              See how our movement is making headlines across the nation
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="bg-[var(--surface-warm)] rounded-[var(--radius-2xl)] p-8 md:p-12 pb-16 md:pb-20"
          >
            <div className="media-carousel-wrapper">
              <Slider {...sliderSettings}>
                {mediaCoverage.map((article, index) => (
                  <div key={index} className="px-3">
                    <motion.a
                      href={article.link}
                      target="_blank"
                      rel="noopener noreferrer"
                      whileHover={{ y: -4 }}
                      transition={{ type: "spring", stiffness: 300 }}
                      className="flex flex-col bg-card rounded-[var(--radius-xl)] p-5 border border-border hover:shadow-[var(--elevation-md)] transition-shadow block h-full"
                      aria-label={`Read article by ${article.source}: ${article.title}`}
                    >
                      <div className="relative w-full h-40 rounded-[var(--radius-lg)] overflow-hidden mb-4">
                        <ImageWithFallback
                          src={article.image}
                          alt={article.title}
                          className="w-full h-full object-cover"
                          width={400}
                          height={225}
                        />
                      </div>
                      <div className="flex justify-between items-center mb-3">
                        <span className="text-accent uppercase tracking-wide" style={{ fontSize: '11px', fontWeight: 'var(--font-weight-bold)', letterSpacing: '0.5px' }}>
                          {article.source}
                        </span>
                        <ExternalLink className="w-4 h-4 text-muted-foreground flex-shrink-0" aria-hidden="true" />
                      </div>
                      <h3 className="text-foreground mb-4 flex-1" style={{ fontSize: '15px', fontWeight: 'var(--font-weight-semibold)', lineHeight: '1.4' }}>
                        {article.title}
                      </h3>
                      <div className="flex items-center gap-1 text-muted-foreground pt-2 border-t border-border" style={{ fontSize: '13px' }}>
                        <Globe className="w-3.5 h-3.5" />
                        <span>Read Full Article</span>
                      </div>
                    </motion.a>
                  </div>
                ))}
              </Slider>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="mt-6 md:mt-8 bg-card rounded-[var(--radius-2xl)] p-5 md:p-6 border-2 border-accent/20"
          >
            <p className="text-center text-muted-foreground">
              <span className="text-accent">Need more resources?</span> Contact us on social media 
              using <span className="text-accent">#{'\u200B'}AnimalWrites</span> and we'll do our best to help.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}