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
      logoUrl: "https://drive.google.com/uc?export=view&id=1A272uEHib6hW1LH1FyESUtFOMHpc8toF"
    },
    { 
      name: "Supporter Organization 2", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1Y5HqpQPrIps0iCehT4g1v5ib-JlRpQKf"
    },
    { 
      name: "Supporter Organization 3", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1YU__H-uvgwncbQSWOA-it9ZWHCOh4mce"
    },
    { 
      name: "Supporter Organization 4", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1Fb0MLsuU5hpT-LmGIsbRtEcmWmAIDxeA"
    },
    { 
      name: "Supporter Organization 5", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1vr5w0-Bot56VhmnuX8FduTFKLRvfX6QD"
    },
    { 
      name: "Supporter Organization 6", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1Uz8Ky89wN1eK8sExy3MteI-s1By45-aH"
    },
    { 
      name: "Supporter Organization 7", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1CxbjaMKP2R9phFKyz_-ep0eZs48xJS0i"
    },
    { 
      name: "Supporter Organization 8", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1-iQwlEIOKRPpPUwAACd8f8EmUM4fk_eo"
    },
    { 
      name: "Supporter Organization 9", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=10xZg2AazmTfK9UpuOxnC12KtkgWRgQEg"
    },
    { 
      name: "Supporter Organization 10", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=13xi_oQF6Z4lo9-TsKQQED3QHDh7tFG5P"
    },
    { 
      name: "Supporter Organization 11", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=16-xyn98rWppfCVXTqIk31dNmUG5S7mrS"
    },
    { 
      name: "Supporter Organization 12", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1B5BcD3yIRkW0RP3mNYaoHUyM_H5tEu8l"
    },
    { 
      name: "Supporter Organization 13", 
      url: "#",
      logoUrl: "https://drive.google.com/uc?export=view&id=1sA5cuL2lSadz93DvIxYTiEuO4k81q37f"
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
                  className="flex-shrink-0 w-[180px] h-[180px] md:w-[200px] md:h-[200px]"
                  aria-label={`Visit ${supporter.name}`}
                >
                  <ImageWithFallback
                    src={supporter.logoUrl}
                    alt={supporter.name}
                    className="w-full h-full object-contain"
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