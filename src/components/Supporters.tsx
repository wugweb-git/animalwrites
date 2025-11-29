import { motion } from "motion/react";
import { Heart } from "lucide-react";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function Supporters() {
  // ========================================
  // TO UPDATE WITH REAL LOGOS:
  // 1. Replace 'logoUrl' with actual logo image URL
  // 2. Update 'name' with organization name
  // 3. Update 'url' with organization website/Instagram
  // ========================================
  
  const supporters = [
    { 
      name: "Supporter Organization 1", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l1.jpeg",
      altText: "Animal welfare organization supporting Animal Writes campaign"
    },
    { 
      name: "Supporter Organization 2", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l2.jpeg",
      altText: "Animal rights NGO backing street dog protection movement"
    },
    { 
      name: "Supporter Organization 3", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l3.jpeg",
      altText: "Partner organization advocating for humane animal treatment"
    },
    { 
      name: "Supporter Organization 4", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l4.jpeg",
      altText: "Supporting partner for India's largest letter petition campaign"
    },
    { 
      name: "Supporter Organization 5", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l5.jpeg",
      altText: "Animal compassion advocate supporting Animal Writes"
    },
    { 
      name: "Supporter Organization 6", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l6.jpeg",
      altText: "Nonprofit organization supporting street dog welfare in India"
    },
    { 
      name: "Supporter Organization 7", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l7.jpeg",
      altText: "Animal welfare supporter backing Supreme Court petition movement"
    },
    { 
      name: "Supporter Organization 8", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l8.jpeg",
      altText: "Community partner for animal rights campaign India"
    },
    { 
      name: "Supporter Organization 9", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l9.jpeg",
      altText: "Supporting organization for compassionate coexistence movement"
    },
    { 
      name: "Supporter Organization 10", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l10.jpeg",
      altText: "Animal protection advocate supporting Animal Writes initiative"
    },
    { 
      name: "Supporter Organization 11", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l11.jpeg",
      altText: "Collaborating partner for street dog advocacy campaign"
    },
    { 
      name: "Supporter Organization 12", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l12.jpeg",
      altText: "Supporting partner for nationwide animal welfare movement"
    },
    { 
      name: "Supporter Organization 13", 
      url: "#",
      logoUrl: "https://vedanshu.design/AW/l13.jpeg",
      altText: "Animal rights supporter backing November 29 campaign"
    },
  ];

  // Duplicate supporters for seamless infinite scroll
  const duplicatedSupporters = [...supporters, ...supporters];

  return (
    <section 
      className="py-16 md:py-24 bg-[#FFF8F3]" 
      aria-labelledby="supporters-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-white px-4 py-2 rounded-full mb-4 shadow-sm"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Heart className="w-5 h-5 text-[#F59E0B]" fill="#F59E0B" aria-hidden="true" />
              </motion.div>
              <span className="text-[#F59E0B]">Standing Together</span>
            </motion.div>
            
            <h2 id="supporters-heading" className="text-2xl md:text-4xl text-[#333333] mb-4">
              Supported By
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-2xl mx-auto">
              Animal Lovers, Animal Activists, Animal Welfare Organisations, Every human who knows 'humanity'
            </p>
          </motion.div>

          {/* Supporter Logos Auto-Scroll */}
          <div className="overflow-hidden relative">
            <motion.div
              className="flex gap-4 md:gap-6"
              animate={{
                x: [0, -((200 + 24) * supporters.length)],
              }}
              transition={{
                x: {
                  repeat: Infinity,
                  repeatType: "loop",
                  duration: 25,
                  ease: "linear",
                },
              }}
            >
              {duplicatedSupporters.map((supporter, index) => (
                <a
                  key={index}
                  href={supporter.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex-shrink-0 w-[180px] h-[90px] md:w-[200px] md:h-[100px] flex items-center justify-center"
                  aria-label={`Visit ${supporter.name}`}
                >
                  <ImageWithFallback
                    src={supporter.logoUrl}
                    alt={supporter.altText}
                    className="max-w-full max-h-full object-contain"
                    width={200}
                    height={100}
                  />
                </a>
              ))}
            </motion.div>
          </div>
        </div>
      </div>
    </section>
  );
}