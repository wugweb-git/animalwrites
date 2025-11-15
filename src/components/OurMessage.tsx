import { Mail } from "lucide-react";
import { motion } from "motion/react";

export function OurMessage() {
  return (
    <section className="py-16 md:py-24 bg-[#FFF8F3]">
      <div className="container mx-auto px-4">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8 }}
            viewport={{ once: true }}
            className="bg-white rounded-3xl p-8 md:p-16 shadow-2xl border border-gray-100"
          >
            <div className="text-center space-y-6 md:space-y-8">
              <motion.div
                className="text-6xl md:text-7xl mb-6"
                animate={{ 
                  scale: [1, 1.1, 1],
                  rotate: [0, 5, -5, 0]
                }}
                transition={{ 
                  duration: 3,
                  repeat: Infinity,
                  ease: "easeInOut"
                }}
                role="img"
                aria-label="Envelope"
              >
                ✉️
              </motion.div>
              
              <motion.h2
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.2 }}
                viewport={{ once: true }}
                className="text-2xl md:text-5xl text-[#333333] leading-tight"
              >
                Write your heart. Seal it with compassion. Post for justice.
              </motion.h2>

              <motion.div
                initial={{ opacity: 0, scale: 0.8 }}
                whileInView={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.6, delay: 0.4 }}
                viewport={{ once: true }}
                className="flex justify-center gap-4 md:gap-6 text-4xl md:text-5xl my-6 md:my-8"
              >
                <motion.span
                  animate={{ y: [0, -10, 0] }}
                  transition={{ duration: 2, repeat: Infinity, delay: 0 }}
                  role="img"
                  aria-label="Dog"
                >
                  🐕
                </motion.span>
                <motion.span
                  animate={{ scale: [1, 1.2, 1] }}
                  transition={{ duration: 2, repeat: Infinity, delay: 0.3 }}
                  role="img"
                  aria-label="Heart"
                >
                  ❤️
                </motion.span>
                <motion.span
                  animate={{ rotate: [0, 10, -10, 0] }}
                  transition={{ duration: 3, repeat: Infinity, delay: 0.6 }}
                  role="img"
                  aria-label="India flag"
                >
                  🇮🇳
                </motion.span>
              </motion.div>

              <motion.div
                initial={{ opacity: 0 }}
                whileInView={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.6 }}
                viewport={{ once: true }}
                className="pt-6 md:pt-8 border-t border-gray-200"
              >
                <p className="text-xs md:text-sm uppercase tracking-wider text-[#F59E0B] mb-2">
                  Join the Movement
                </p>
                <p className="text-[#666666] text-sm md:text-lg">
                  #SaveIndianStreetDogs • #NoDogsNoVotes • #AnimalWrites
                </p>
              </motion.div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}