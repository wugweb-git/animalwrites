import { motion } from "motion/react";
import { Camera, Heart } from "lucide-react";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function CampaignGallery() {
  const images = [
    { url: "https://vedanshu.design/AW/im1.jpeg", alt: "Campaign moment 1" },
    { url: "https://vedanshu.design/AW/im2.jpeg", alt: "Campaign moment 2" },
    { url: "https://vedanshu.design/AW/im3.jpeg", alt: "Campaign moment 3" },
    { url: "https://vedanshu.design/AW/im4.jpeg", alt: "Campaign moment 4" },
    { url: "https://vedanshu.design/AW/im5.jpeg", alt: "Campaign moment 5" },
    { url: "https://vedanshu.design/AW/im6.jpeg", alt: "Campaign moment 6" },
    { url: "https://vedanshu.design/AW/im7.jpeg", alt: "Campaign moment 7" },
    { url: "https://vedanshu.design/AW/im8.jpeg", alt: "Campaign moment 8" },
    { url: "https://vedanshu.design/AW/im9.jpeg", alt: "Campaign moment 9" },
    { url: "https://vedanshu.design/AW/im10.jpeg", alt: "Campaign moment 10" },
    { url: "https://vedanshu.design/AW/im11.jpeg", alt: "Campaign moment 11" },
    { url: "https://vedanshu.design/AW/im12.jpeg", alt: "Campaign moment 12" },
    { url: "https://vedanshu.design/AW/im13.jpeg", alt: "Campaign moment 13" },
  ];

  return (
    <section 
      className="py-16 md:py-24 bg-white" 
      aria-labelledby="gallery-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-7xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-[#FFF8F3] px-4 py-2 rounded-full mb-4 shadow-sm"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Camera className="w-5 h-5 text-[#F59E0B]" aria-hidden="true" />
              </motion.div>
              <span className="text-[#F59E0B]">Our Movement</span>
            </motion.div>
            
            <h2 id="gallery-heading" className="text-2xl md:text-4xl text-[#333333] mb-4">
              Moments From Previous Campaigns
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-2xl mx-auto">
              A glimpse of the compassion and unity that drives our movement forward
            </p>
          </motion.div>

          {/* Gallery Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            {images.map((image, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.05 }}
                viewport={{ once: true }}
                whileHover={{ scale: 1.03 }}
                className="relative group overflow-hidden rounded-xl shadow-lg aspect-square"
              >
                <ImageWithFallback
                  src={image.url}
                  alt={image.alt}
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                />
                
                {/* Overlay on hover */}
                <div className="absolute inset-0 bg-gradient-to-t from-[#333333]/80 via-[#333333]/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center p-6">
                  <motion.div
                    initial={{ y: 20, opacity: 0 }}
                    whileHover={{ y: 0, opacity: 1 }}
                    className="flex items-center gap-2 text-white"
                  >
                    <Heart className="w-5 h-5 text-[#F59E0B]" fill="#F59E0B" aria-hidden="true" />
                    <span className="text-sm">Together for Change</span>
                  </motion.div>
                </div>
              </motion.div>
            ))}
          </div>

          {/* Bottom message */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="mt-12 md:mt-16 text-center"
          >
            <p className="text-[#666666] text-base md:text-lg max-w-3xl mx-auto">
              Every letter, every voice, every act of compassion brings us closer to a more humane society. Join us in making history on November 29th, 2025.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
