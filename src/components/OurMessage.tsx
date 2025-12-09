import { Heart, Quote } from "lucide-react";
import { motion } from "motion/react";

export function OurMessage() {
  return (
    <section className="py-12 md:py-16 bg-gradient-to-b from-white via-[#FFF8F3] to-white relative overflow-hidden">
      {/* Decorative background elements */}
      <div className="absolute inset-0 opacity-30" aria-hidden="true">
        <div className="absolute top-20 left-10 w-20 h-20 bg-[#F59E0B]/10 rounded-full blur-3xl" />
        <div className="absolute bottom-20 right-10 w-32 h-32 bg-[#D97706]/10 rounded-full blur-3xl" />
        <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-40 h-40 bg-[#F59E0B]/5 rounded-full blur-3xl" />
      </div>

      <div className="container mx-auto px-4 relative z-10">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8 }}
            viewport={{ once: true }}
            className="bg-white rounded-3xl p-6 md:p-10 shadow-2xl border-2 border-[#F59E0B]/20 relative overflow-hidden"
          >
            {/* Decorative corner accents */}
            <div className="absolute top-0 left-0 w-16 h-16 border-t-4 border-l-4 border-[#F59E0B] rounded-tl-3xl" aria-hidden="true" />
            <div className="absolute bottom-0 right-0 w-16 h-16 border-b-4 border-r-4 border-[#F59E0B] rounded-br-3xl" aria-hidden="true" />

            <div className="text-center space-y-4 md:space-y-5">
              {/* Gandhi Quote */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.1 }}
                viewport={{ once: true }}
                className="bg-gradient-to-br from-[#FFF8F3] via-[#FFF0E0] to-[#FFF8F3] rounded-2xl p-5 md:p-7 relative border-2 border-[#F59E0B]/20 shadow-lg"
              >
                {/* Gandhi silhouette icon */}
                <motion.div
                  className="w-14 h-14 md:w-16 md:h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-[#F59E0B] to-[#D97706] p-3 shadow-lg"
                  animate={{ 
                    boxShadow: [
                      "0 5px 15px rgba(245, 158, 11, 0.3)",
                      "0 8px 25px rgba(245, 158, 11, 0.5)",
                      "0 5px 15px rgba(245, 158, 11, 0.3)"
                    ]
                  }}
                  transition={{ 
                    duration: 3,
                    repeat: Infinity,
                    ease: "easeInOut"
                  }}
                >
                  {/* Gandhi glasses icon */}
                  <svg viewBox="0 0 100 100" className="w-full h-full text-white" fill="currentColor">
                    <circle cx="30" cy="50" r="18" stroke="currentColor" strokeWidth="4" fill="none" />
                    <circle cx="70" cy="50" r="18" stroke="currentColor" strokeWidth="4" fill="none" />
                    <line x1="48" y1="50" x2="52" y2="50" stroke="currentColor" strokeWidth="4" />
                    <line x1="12" y1="50" x2="5" y2="45" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                    <line x1="88" y1="50" x2="95" y2="45" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                  </svg>
                </motion.div>
                
                <Quote className="w-6 h-6 md:w-7 md:h-7 text-[#F59E0B] mx-auto mb-3 opacity-40" aria-hidden="true" />
                <p className="text-[#333333] italic text-base md:text-xl leading-relaxed mb-3">
                  "The greatness of a nation and its moral progress can be judged by the way its animals are treated."
                </p>
                <p className="text-[#D97706] text-sm md:text-base">
                  — Mahatma Gandhi
                </p>
              </motion.div>

              <motion.h2
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.3 }}
                viewport={{ once: true }}
                className="text-2xl md:text-5xl text-[#333333] leading-tight px-4"
              >
                <span className="inline-block">Write your heart.</span>{" "}
                <span className="inline-block text-[#F59E0B]">Seal it with compassion.</span>{" "}
                <span className="inline-block">Post for justice.</span>
              </motion.h2>

              <motion.div
                initial={{ opacity: 0, scale: 0.8 }}
                whileInView={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.6, delay: 0.4 }}
                viewport={{ once: true }}
                className="flex justify-center gap-6 md:gap-8 my-4 md:my-5"
              >
                <motion.div
                  className="relative"
                  whileHover={{ scale: 1.2 }}
                  animate={{ y: [0, -10, 0] }}
                  transition={{ duration: 2, repeat: Infinity, delay: 0 }}
                >
                  <div className="text-4xl md:text-5xl" role="img" aria-label="Dog">
                    🐕
                  </div>
                  <motion.div
                    className="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-12 h-1 bg-[#F59E0B]/30 rounded-full blur-sm"
                    animate={{ scale: [1, 1.2, 1], opacity: [0.3, 0.6, 0.3] }}
                    transition={{ duration: 2, repeat: Infinity }}
                    aria-hidden="true"
                  />
                </motion.div>

                <motion.div
                  className="relative"
                  whileHover={{ scale: 1.2 }}
                  animate={{ scale: [1, 1.15, 1] }}
                  transition={{ duration: 2, repeat: Infinity, delay: 0.3 }}
                >
                  <div className="text-4xl md:text-5xl" role="img" aria-label="Heart">
                    ❤️
                  </div>
                  <motion.div
                    className="absolute inset-0 bg-red-500/20 rounded-full blur-xl"
                    animate={{ scale: [0.8, 1.2, 0.8], opacity: [0.2, 0.4, 0.2] }}
                    transition={{ duration: 2, repeat: Infinity, delay: 0.3 }}
                    aria-hidden="true"
                  />
                </motion.div>

                <motion.div
                  className="relative"
                  whileHover={{ scale: 1.2 }}
                  animate={{ rotate: [0, 10, -10, 0] }}
                  transition={{ duration: 3, repeat: Infinity, delay: 0.6 }}
                >
                  <div className="text-4xl md:text-5xl" role="img" aria-label="India flag">
                    🇮🇳
                  </div>
                  <motion.div
                    className="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-12 h-1 bg-[#F59E0B]/30 rounded-full blur-sm"
                    animate={{ scale: [1, 1.2, 1], opacity: [0.3, 0.6, 0.3] }}
                    transition={{ duration: 3, repeat: Infinity, delay: 0.6 }}
                    aria-hidden="true"
                  />
                </motion.div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.5 }}
                viewport={{ once: true }}
                className="pt-4 md:pt-5 mt-4 border-t-2 border-dashed border-[#F59E0B]/30"
              >
                <motion.div
                  className="inline-flex items-center gap-2 bg-gradient-to-r from-[#F59E0B] to-[#D97706] text-white px-3 py-1.5 rounded-full text-xs uppercase tracking-wider mb-3 shadow-lg"
                  animate={{
                    boxShadow: [
                      "0 4px 15px rgba(245, 158, 11, 0.3)",
                      "0 6px 20px rgba(245, 158, 11, 0.5)",
                      "0 4px 15px rgba(245, 158, 11, 0.3)"
                    ]
                  }}
                  transition={{ duration: 2, repeat: Infinity }}
                >
                  <Heart className="w-3 h-3" aria-hidden="true" />
                  Join the Movement
                </motion.div>
                
                <div className="flex flex-wrap justify-center gap-2 md:gap-3">
                  {["#SaveIndianStreetDogs", "#NoDogsNoVotes", "#AnimalWrites"].map((tag, index) => (
                    <motion.span
                      key={tag}
                      initial={{ opacity: 0, scale: 0.8 }}
                      whileInView={{ opacity: 1, scale: 1 }}
                      transition={{ duration: 0.4, delay: 0.6 + index * 0.1 }}
                      viewport={{ once: true }}
                      whileHover={{ scale: 1.05, y: -2 }}
                      className="inline-block bg-[#FFF8F3] border-2 border-[#F59E0B]/30 text-[#D97706] px-3 py-1.5 rounded-full text-xs md:text-sm hover:border-[#F59E0B] hover:shadow-md transition-all cursor-pointer"
                    >
                      {tag}
                    </motion.span>
                  ))}
                </div>
              </motion.div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}