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
      name: "Nawab Tails Rescue", 
      url: "https://www.instagram.com/nawabitailsrescue",
      logoUrl: "https://images.unsplash.com/photo-1584867818838-5312e821fe15?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhbmltYWwlMjBzaGVsdGVyJTIwbG9nb3xlbnwxfHx8fDE3NjMyMTQwOTN8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Animal Shelter Organization", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1592275142414-8588d1e87e11?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkb2clMjByZXNjdWUlMjBvcmdhbml6YXRpb258ZW58MXx8fHwxNzYzMjE0MDkzfDA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Animal Welfare NGO", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1762783667040-0d38c74c75c3?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhbmltYWwlMjB3ZWxmYXJlJTIwbmdvfGVufDF8fHx8MTc2MzIxNDA5M3ww&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Pet Care Foundation", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1563460716037-460a3ad24ba9?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwZXQlMjBjYXJlJTIwb3JnYW5pemF0aW9ufGVufDF8fHx8MTc2MzIxNDA5M3ww&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Animal Rights Collective", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1635951279634-1143dc8c4038?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhbmltYWwlMjByaWdodHMlMjBjaGFyaXR5fGVufDF8fHx8MTc2MzIxNDA5NHww&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Dog Rescue Initiative", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1444212477490-ca407925329e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkb2clMjBjaGFyaXR5JTIwb3JnYW5pemF0aW9ufGVufDF8fHx8MTc2MzIxNDA5NHww&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Compassion Street Dogs", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1584867818838-5312e821fe15?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhbmltYWwlMjBzaGVsdGVyJTIwbG9nb3xlbnwxfHx8fDE3NjMyMTQwOTN8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
    },
    { 
      name: "Stray Support Network", 
      url: "#",
      logoUrl: "https://images.unsplash.com/photo-1592275142414-8588d1e87e11?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkb2clMjByZXNjdWUlMjBvcmdhbml6YXRpb258ZW58MXx8fHwxNzYzMjE0MDkzfDA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
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
              Animal shelters, NGOs, and rescue organizations across India united for compassion
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
                  className="flex-shrink-0 w-[180px] md:w-[200px] bg-white rounded-xl p-4 flex flex-col items-center justify-center hover:shadow-lg transition-all duration-300 border-2 border-gray-100 hover:border-[#F59E0B] group"
                  aria-label={`Visit ${supporter.name}`}
                >
                  {/* Logo Image */}
                  <div className="w-full aspect-square mb-3 overflow-hidden rounded-lg">
                    <motion.div
                      whileHover={{ scale: 1.1 }}
                      transition={{ duration: 0.3 }}
                    >
                      <ImageWithFallback
                        src={supporter.logoUrl}
                        alt={supporter.name}
                        className="w-full h-full object-cover"
                      />
                    </motion.div>
                  </div>
                  <p className="text-xs md:text-sm text-center text-[#666666] group-hover:text-[#F59E0B] transition-colors">
                    {supporter.name}
                  </p>
                </a>
              ))}
            </motion.div>
          </div>

          {/* Add Your Organization CTA */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="text-center mt-10 md:mt-12"
          >
            <div className="bg-white rounded-2xl p-6 md:p-8 border-2 border-[#F59E0B]/20">
              <p className="text-[#666666] mb-4 text-sm md:text-base">
                Does your organization support this movement?
              </p>
              <motion.a
                href="https://www.facebook.com/AnimalWritesIndia"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 text-[#F59E0B] hover:underline focus:outline-none focus:ring-2 focus:ring-[#F59E0B] rounded px-2 py-1"
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                Contact us to be featured
              </motion.a>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}