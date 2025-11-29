import { motion } from "motion/react";

export function AboutMovement() {
  return (
    <section 
      id="about-movement"
      className="py-16 md:py-24 bg-white"
      aria-labelledby="about-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
            {/* Left Column - Text */}
            <motion.div
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
              viewport={{ once: true }}
              className="space-y-6 order-2 md:order-1"
            >
              <h2 id="about-heading" className="text-3xl md:text-5xl text-[#333333] leading-tight font-bold">
                A National Movement of Compassion
              </h2>
              
              <div className="space-y-4 text-base md:text-lg text-[#666666] leading-relaxed">
                <p>
                  On 29th November, thousands across India will walk to their local General Post Offices 
                  to post handwritten letters to the Hon'ble Supreme Court of India.
                </p>
                
                <p>
                  These letters represent <strong className="text-[#333333]">unity</strong>, 
                  <strong className="text-[#333333]"> civic responsibility</strong>, and the 
                  <strong className="text-[#333333]"> collective voice</strong> of a country that 
                  believes in coexistence.
                </p>
              </div>

              <motion.div
                initial={{ opacity: 0, x: -20 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.6, delay: 0.3 }}
                viewport={{ once: true }}
                className="bg-[#FFF8F3] rounded-xl p-5 md:p-6 border-l-4 border-[#F59E0B]"
              >
                <p className="text-[#333333] italic text-sm md:text-base">
                  This is not a protest — it's a peaceful national letter movement for justice and empathy.
                </p>
              </motion.div>
            </motion.div>

            {/* Right Column - Illustration */}
            <motion.div
              initial={{ opacity: 0, x: 30 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              viewport={{ once: true }}
              className="flex justify-center order-1 md:order-2"
            >
              <div className="relative w-full max-w-md">
                {/* Background decorative element */}
                <motion.div
                  className="absolute inset-0 bg-[#FFF8F3] rounded-3xl"
                  animate={{ rotate: [6, 8, 6] }}
                  transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
                  aria-hidden="true"
                />
                
                {/* Main illustration container */}
                <div className="relative bg-white rounded-2xl shadow-2xl p-6 md:p-12 border border-gray-100">
                  <div className="space-y-6 md:space-y-8">
                    {/* Stack of envelopes illustration */}
                    <div className="space-y-4">
                      <motion.div
                        className="bg-[#333333] rounded-lg p-4 md:p-6 shadow-lg"
                        initial={{ rotate: 3, y: 0 }}
                        whileHover={{ rotate: 6, y: -5 }}
                        transition={{ type: "spring", stiffness: 300 }}
                      >
                        <div className="flex items-center gap-3 text-white">
                          <motion.span
                            className="text-2xl md:text-3xl"
                            animate={{ scale: [1, 1.1, 1] }}
                            transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                            role="img"
                            aria-label="Envelope"
                          >
                            ✉️
                          </motion.span>
                          <div className="flex-1">
                            <div className="h-2 bg-white/30 rounded mb-2"></div>
                            <div className="h-2 bg-white/30 rounded w-3/4"></div>
                          </div>
                        </div>
                      </motion.div>
                      
                      <motion.div
                        className="bg-[#F5F5F5] rounded-lg p-4 md:p-6 shadow-lg border-2 border-[#F59E0B]/20"
                        initial={{ rotate: -2, y: 0 }}
                        whileHover={{ rotate: -4, y: -5 }}
                        transition={{ type: "spring", stiffness: 300 }}
                      >
                        <div className="flex items-center gap-3 text-[#333333]">
                          <motion.span
                            className="text-2xl md:text-3xl"
                            animate={{ scale: [1, 1.1, 1] }}
                            transition={{ duration: 2, repeat: Infinity, delay: 0.3, ease: "easeInOut" }}
                            role="img"
                            aria-label="Envelope"
                          >
                            ✉️
                          </motion.span>
                          <div className="flex-1">
                            <div className="h-2 bg-[#F59E0B]/20 rounded mb-2"></div>
                            <div className="h-2 bg-[#F59E0B]/20 rounded w-2/3"></div>
                          </div>
                        </div>
                      </motion.div>
                      
                      <motion.div
                        className="bg-white rounded-lg p-4 md:p-6 border-2 border-[#F59E0B] shadow-lg"
                        initial={{ rotate: 0, y: 0 }}
                        whileHover={{ rotate: 2, y: -5 }}
                        transition={{ type: "spring", stiffness: 300 }}
                      >
                        <div className="flex items-center gap-3 text-[#333333]">
                          <motion.span
                            className="text-2xl md:text-3xl"
                            animate={{ scale: [1, 1.1, 1] }}
                            transition={{ duration: 2, repeat: Infinity, delay: 0.6, ease: "easeInOut" }}
                            role="img"
                            aria-label="Envelope"
                          >
                            ✉️
                          </motion.span>
                          <div className="flex-1">
                            <div className="h-2 bg-[#F59E0B]/30 rounded mb-2"></div>
                            <div className="h-2 bg-[#F59E0B]/30 rounded w-4/5"></div>
                          </div>
                        </div>
                      </motion.div>
                    </div>

                    {/* Caption */}
                    <motion.div
                      initial={{ opacity: 0 }}
                      whileInView={{ opacity: 1 }}
                      transition={{ duration: 0.6, delay: 0.5 }}
                      viewport={{ once: true }}
                      className="text-center"
                    >
                      <p className="text-[#333333] text-sm md:text-base">
                        Thousands of letters. One voice. United for compassion.
                      </p>
                    </motion.div>
                  </div>
                </div>
              </div>
            </motion.div>
          </div>
        </div>
      </div>
    </section>
  );
}