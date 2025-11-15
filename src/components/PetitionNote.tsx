import { Scale, Mail, Users, CheckCircle } from "lucide-react";
import { motion } from "motion/react";

export function PetitionNote() {
  return (
    <section 
      className="py-16 md:py-24 bg-white" 
      id="petition-note"
      aria-labelledby="petition-note-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-[#FFF8F3] px-4 py-2 rounded-full mb-4 border border-[#D32F2F]/20"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <Scale className="w-5 h-5 text-[#D32F2F]" aria-hidden="true" />
              <span className="text-[#D32F2F]">Constitutional Right</span>
            </motion.div>
            <h2 id="petition-note-heading" className="text-2xl md:text-4xl text-[#333333] mb-4">
              Why This Matters Legally
            </h2>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.1 }}
            viewport={{ once: true }}
            className="space-y-6 md:space-y-8"
          >
            {/* Introduction */}
            <motion.div
              className="bg-[#FFF8F3] rounded-2xl p-6 md:p-8 border-2 border-[#D32F2F]/20"
              whileHover={{ borderColor: "rgba(211, 47, 47, 0.4)" }}
              transition={{ duration: 0.3 }}
            >
              <h3 className="text-[#D32F2F] text-lg md:text-xl mb-4">
                Lucknow Animal Lovers Community
              </h3>
              <p className="text-[#666666] leading-relaxed mb-4 text-sm md:text-base">
                Lucknow's animal welfare community has taken a constitutional, peaceful, and unprecedented step to protect India's stray dogs.
              </p>
              <p className="text-[#666666] leading-relaxed mb-4 text-sm md:text-base">
                The Supreme Court's recent order dated <strong className="text-[#333333]">07.11.2025</strong> has left citizens divided and deeply concerned. Unlike opposition to a government policy, this issue concerns a court order, which requires a constitutional and legally permissible form of response.
              </p>
              <p className="text-[#333333] mt-4 text-sm md:text-base">
                This is where the power of a <strong className="text-[#D32F2F]">Letter Petition</strong> comes in.
              </p>
            </motion.div>

            {/* Why a Letter Petition */}
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              viewport={{ once: true }}
              className="bg-white rounded-2xl p-6 md:p-8 border-2 border-gray-200 hover:border-[#D32F2F] transition-colors"
            >
              <div className="flex items-start gap-4 mb-4">
                <motion.div
                  whileHover={{ rotate: 360, scale: 1.1 }}
                  transition={{ duration: 0.6 }}
                >
                  <Scale className="w-6 h-6 text-[#D32F2F] flex-shrink-0 mt-1" aria-hidden="true" />
                </motion.div>
                <div>
                  <h3 className="text-[#333333] text-lg md:text-xl mb-4">Why a Letter Petition?</h3>
                  <p className="text-[#666666] leading-relaxed text-sm md:text-base">
                    In <em className="text-[#333333]">SP Gupta vs Union of India</em>, the Supreme Court held that it can take cognizance of issues of public importance based on a simple letter — without formal pleadings or locus standi.
                  </p>
                </div>
              </div>
              <div className="bg-[#FFF8F3] rounded-xl p-4 md:p-6 mt-4 border-l-4 border-[#D32F2F]">
                <p className="text-[#333333] text-sm md:text-base">
                  This is a rare and powerful constitutional tool available to every citizen.
                </p>
                <p className="text-[#666666] text-xs md:text-sm mt-2">
                  Letter petitions have been used many times in history, but never in a coordinated, nationwide format.
                </p>
              </div>
            </motion.div>

            {/* Why This Campaign Is Unique */}
            <motion.div
              initial={{ opacity: 0, x: 20 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.3 }}
              viewport={{ once: true }}
              className="bg-white rounded-2xl p-6 md:p-8 border-2 border-gray-200 hover:border-[#D32F2F] transition-colors"
            >
              <div className="flex items-start gap-4 mb-6">
                <motion.div
                  animate={{ rotate: [0, 10, -10, 0] }}
                  transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
                >
                  <Mail className="w-6 h-6 text-[#D32F2F] flex-shrink-0 mt-1" aria-hidden="true" />
                </motion.div>
                <h3 className="text-[#333333] text-lg md:text-xl">Why This Campaign Is Unique</h3>
              </div>
              <div className="space-y-4">
                {[
                  "A handwritten letter carries legal weight and seriousness emails cannot.",
                  "Posting letters simultaneously across GPOs nationwide creates a peaceful civic gathering — not a protest.",
                  "Thousands standing in postal queues sends a massive visual signal, attracting both national and international attention."
                ].map((text, index) => (
                  <motion.div
                    key={index}
                    className="flex items-start gap-3"
                    initial={{ opacity: 0, x: -10 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.4, delay: index * 0.1 }}
                    viewport={{ once: true }}
                  >
                    <motion.div
                      whileHover={{ scale: 1.2, rotate: 360 }}
                      transition={{ duration: 0.5 }}
                    >
                      <CheckCircle className="w-5 h-5 text-[#D32F2F] flex-shrink-0 mt-1" aria-hidden="true" />
                    </motion.div>
                    <p className="text-[#666666] leading-relaxed text-sm md:text-base">
                      <span dangerouslySetInnerHTML={{ __html: text.replace(/handwritten letter|simultaneously across GPOs nationwide|massive visual signal/g, match => `<strong class="text-[#333333]">${match}</strong>`) }} />
                    </p>
                  </motion.div>
                ))}
                
                <motion.div
                  className="flex items-start gap-3"
                  initial={{ opacity: 0, x: -10 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.4, delay: 0.3 }}
                  viewport={{ once: true }}
                >
                  <motion.div
                    whileHover={{ scale: 1.2, rotate: 360 }}
                    transition={{ duration: 0.5 }}
                  >
                    <CheckCircle className="w-5 h-5 text-[#D32F2F] flex-shrink-0 mt-1" aria-hidden="true" />
                  </motion.div>
                  <div className="flex-1">
                    <p className="text-[#666666] leading-relaxed mb-2 text-sm md:text-base">
                      A uniform title —
                    </p>
                    <motion.div
                      className="bg-[#FFF8F3] rounded-lg p-4 border border-[#D32F2F]/20"
                      whileHover={{ scale: 1.02 }}
                      transition={{ type: "spring", stiffness: 300 }}
                    >
                      <p className="text-[#D32F2F] text-center italic text-sm md:text-base">
                        "IN RE: Compassion, Kindness & Humanity"
                      </p>
                    </motion.div>
                    <p className="text-[#666666] leading-relaxed mt-2 text-sm md:text-base">
                      — helps the Court treat this as a consolidated national plea.
                    </p>
                  </div>
                </motion.div>
              </div>
            </motion.div>

            {/* Closing Statement */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.4 }}
              viewport={{ once: true }}
              className="bg-gradient-to-br from-[#D32F2F] to-[#B71C1C] rounded-2xl p-8 md:p-10 text-center relative overflow-hidden"
            >
              {/* Animated background */}
              <div className="absolute inset-0 opacity-10" aria-hidden="true">
                {[...Array(8)].map((_, i) => (
                  <motion.div
                    key={i}
                    className="absolute w-2 h-2 bg-white rounded-full"
                    style={{
                      left: `${Math.random() * 100}%`,
                      top: `${Math.random() * 100}%`,
                    }}
                    animate={{
                      scale: [1, 1.5, 1],
                      opacity: [0.3, 0.6, 0.3],
                    }}
                    transition={{
                      duration: 3,
                      repeat: Infinity,
                      delay: i * 0.4,
                      ease: "easeInOut",
                    }}
                  />
                ))}
              </div>
              
              <motion.div
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
              >
                <Users className="w-10 h-10 md:w-12 md:h-12 text-white mx-auto mb-4" aria-hidden="true" />
              </motion.div>
              <p className="text-white text-lg md:text-xl mb-2 relative z-10">
                This is more than an appeal.
              </p>
              <p className="text-white/90 text-base md:text-lg leading-relaxed relative z-10">
                It is India's largest exercise of a constitutional right — a movement rooted in dignity, empathy, and lawful action.
              </p>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}