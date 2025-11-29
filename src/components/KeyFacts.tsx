import { AlertTriangle, Home, TrendingUp, CheckCircle } from "lucide-react";
import { motion } from "motion/react";

export function KeyFacts() {
  const facts = [
    {
      icon: AlertTriangle,
      title: "The Challenge",
      stat: "60+ Million",
      description: "Estimated stray dogs in India with no government infrastructure to house them",
    },
    {
      icon: Home,
      title: "Infrastructure Gap",
      stat: "Near Zero",
      description: "Government-run animal shelters available across the country",
    },
    {
      icon: CheckCircle,
      title: "Proven Solution",
      stat: "ABC Programs",
      description: "Animal Birth Control programs have successfully reduced stray populations in Kerala and other states",
    },
    {
      icon: TrendingUp,
      title: "Cost Effective",
      stat: "90% Cheaper",
      description: "ABC programs cost significantly less than relocation and are more humane",
    },
  ];

  return (
    <section 
      className="py-16 md:py-24 bg-white" 
      id="key-facts"
      aria-labelledby="key-facts-heading"
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
              className="inline-flex items-center gap-2 bg-[#FFF8F3] px-4 py-2 rounded-full mb-4 border border-[#F59E0B]/20"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <CheckCircle className="w-5 h-5 text-[#333333]" aria-hidden="true" />
              <span className="text-[#333333]">Evidence-Based</span>
            </motion.div>
            <h2 id="key-facts-heading" className="text-2xl md:text-4xl text-[#333333] mb-4 font-bold">
              The Facts Speak for Themselves
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-2xl mx-auto">
              Understanding the ground reality and viable solutions
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-4 md:gap-6 mb-12">
            {facts.map((fact, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                viewport={{ once: true }}
              >
                <motion.div
                  className="bg-white rounded-2xl p-6 md:p-8 border-2 border-gray-200 hover:border-[#F59E0B] hover:shadow-lg transition-all h-full"
                  whileHover={{ y: -5 }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <div className="flex items-start gap-4 mb-4">
                    <motion.div
                      className="bg-[#FFF8F3] rounded-full p-3"
                      whileHover={{ rotate: 360, scale: 1.1 }}
                      transition={{ duration: 0.6 }}
                    >
                      <fact.icon className="w-6 h-6 text-[#333333]" aria-hidden="true" />
                    </motion.div>
                    <div className="flex-1">
                      <h3 className="text-[#333333] mb-2 text-base md:text-lg">{fact.title}</h3>
                      <motion.div
                        className="text-2xl md:text-3xl text-[#D97706] mb-3"
                        initial={{ scale: 0.8, opacity: 0 }}
                        whileInView={{ scale: 1, opacity: 1 }}
                        transition={{ duration: 0.5, delay: index * 0.1 + 0.2 }}
                        viewport={{ once: true }}
                      >
                        {fact.stat}
                      </motion.div>
                    </div>
                  </div>
                  <p className="text-[#666666] leading-relaxed text-sm md:text-base">{fact.description}</p>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="bg-[#FFF8F3] rounded-2xl p-6 md:p-12 border-2 border-[#F59E0B]/20"
          >
            <h3 className="text-[#D97706] text-lg md:text-xl mb-6 md:mb-8 text-center font-bold">Why ABC Programs Work</h3>
            <div className="grid sm:grid-cols-2 md:grid-cols-3 gap-6 md:gap-8">
              {[
                {
                  title: "Humane",
                  desc: "Dogs are sterilized, vaccinated, and returned to their communities"
                },
                {
                  title: "Effective",
                  desc: "Prevents new births, gradually reducing population over time"
                },
                {
                  title: "Sustainable",
                  desc: "Community involvement ensures long-term success"
                }
              ].map((item, index) => (
                <motion.div
                  key={index}
                  className="flex items-start gap-3"
                  initial={{ opacity: 0, x: -20 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.4, delay: index * 0.1 }}
                  viewport={{ once: true }}
                >
                  <motion.div
                    whileHover={{ scale: 1.2, rotate: 360 }}
                    transition={{ duration: 0.5 }}
                  >
                    <CheckCircle className="w-5 h-5 text-[#333333] flex-shrink-0 mt-1" aria-hidden="true" />
                  </motion.div>
                  <div>
                    <p className="text-[#333333] mb-1 font-medium">{item.title}</p>
                    <p className="text-[#666666] text-sm md:text-base">
                      {item.desc}
                    </p>
                  </div>
                </motion.div>
              ))}
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}