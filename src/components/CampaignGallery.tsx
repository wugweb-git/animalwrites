import { motion } from "motion/react";
import { Camera, Heart } from "lucide-react";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function CampaignGallery() {
  const images = [
    { url: "https://vedanshu.design/AW/im1.jpeg", alt: "Animal Writes supporters gathering for street dog welfare campaign in India" },
    { url: "https://vedanshu.design/AW/im2.jpeg", alt: "Citizens participating in Animal Writes letter petition movement for animal rights" },
    { url: "https://vedanshu.design/AW/im3.jpeg", alt: "Community members writing letters to Supreme Court advocating for street dogs" },
    { url: "https://vedanshu.design/AW/im4.jpeg", alt: "Animal Writes campaign event promoting humane coexistence with street dogs" },
    { url: "https://vedanshu.design/AW/im5.jpeg", alt: "Volunteers at Animal Writes movement supporting animal welfare in India" },
    { url: "https://vedanshu.design/AW/im6.jpeg", alt: "Animal rights activists gathering for street dog protection campaign" },
    { url: "https://vedanshu.design/AW/im7.jpeg", alt: "Citizens united for Animal Writes letter petition to Indian Supreme Court" },
    { url: "https://vedanshu.design/AW/im8.jpeg", alt: "Community engagement in Animal Writes campaign for compassionate animal welfare" },
    { url: "https://vedanshu.design/AW/im9.jpeg", alt: "Animal Writes supporters advocating for ABC program and street dog rights" },
    { url: "https://vedanshu.design/AW/im10.jpeg", alt: "Grassroots mobilization for Animal Writes national letter campaign 2025" },
    { url: "https://vedanshu.design/AW/im11.jpeg", alt: "Animal welfare advocates participating in India's largest petition movement" },
    { url: "https://vedanshu.design/AW/im12.jpeg", alt: "Animal Writes campaign volunteers promoting humane treatment of street dogs" },
    { url: "https://vedanshu.design/AW/im13.jpeg", alt: "Community gathering for Animal Writes movement supporting animal rights in India" },
  ];

  return (
    <section 
      id="gallery"
      className="py-16 md:py-24 bg-background" 
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
              className="inline-flex items-center gap-2 bg-[var(--surface-warm)] px-4 py-2 rounded-[var(--radius-full)] mb-4 shadow-[var(--elevation-sm)]"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Camera className="w-5 h-5" style={{ color: 'var(--accent)' }} aria-hidden="true" />
              </motion.div>
              <span style={{ color: 'var(--primary)' }}>Our Movement</span>
            </motion.div>
            
            <h2 id="gallery-heading" style={{ color: 'var(--primary)' }} className="mb-4">
              Moments From Previous Campaigns
            </h2>
            <p style={{ color: 'var(--muted-foreground)' }} className="max-w-2xl mx-auto">
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
                className={`relative group overflow-hidden rounded-[var(--radius-xl)] shadow-[var(--elevation-lg)] aspect-square ${index === 12 ? 'hidden' : ''}`}
              >
                <ImageWithFallback
                  src={image.url}
                  alt={image.alt}
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                  width={600}
                  height={600}
                />
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
            <p className="text-muted-foreground max-w-3xl mx-auto">
              Every letter, every voice, every act of compassion brings us closer to a more humane society. Join us in making history on November 29th, 2025.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}