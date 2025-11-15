import { AlertCircle, Building2, Heart } from "lucide-react";
import { motion } from "motion/react";

export function WhyThisMatters() {
  const reasons = [
    {
      icon: Building2,
      title: "No Infrastructure",
      description: "India lacks government-run shelters to house millions of street dogs. Removal is not practical.",
    },
    {
      icon: AlertCircle,
      title: "Inevitable Suffering",
      description: "Forced relocation will cause immense suffering and chaos for both animals and communities.",
    },
    {
      icon: Heart,
      title: "Humane Solutions Exist",
      description: "We request the Court adopt realistic, sustainable solutions like ABC programs and community care.",
    },
  ];

  return (
    <section 
      id="why-matters" 
      className="py-16 md:py-24 bg-[#F5F5F5]"
      aria-labelledby="why-matters-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="why-matters-heading" className="text-3xl md:text-5xl text-[#333333] mb-4 md:mb-6">
              Why This Matters
            </h2>
            <p className="text-lg md:text-xl text-[#666666] max-w-3xl mx-auto">
              This is not a protest — it's a peaceful national letter movement for justice and empathy.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 md:grid-cols-3 gap-6 md:gap-8 mb-12">
            {reasons.map((reason, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.15, duration: 0.5 }}
                viewport={{ once: true }}
              >
                <motion.div
                  className="bg-white rounded-2xl p-6 md:p-8 shadow-lg hover:shadow-xl transition-all h-full"
                  whileHover={{ y: -5, scale: 1.02 }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <motion.div
                    className="bg-[#FFF8F3] rounded-full w-14 h-14 md:w-16 md:h-16 flex items-center justify-center mb-4 md:mb-6 mx-auto sm:mx-0"
                    whileHover={{ rotate: 360, scale: 1.1 }}
                    transition={{ duration: 0.6 }}
                  >
                    <reason.icon className="w-7 h-7 md:w-8 md:h-8 text-[#D32F2F]" aria-hidden="true" />
                  </motion.div>
                  <h3 className="text-lg md:text-xl text-[#333333] mb-2 md:mb-3 text-center sm:text-left">{reason.title}</h3>
                  <p className="text-sm md:text-base text-[#666666] leading-relaxed text-center sm:text-left">{reason.description}</p>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
          >
            <motion.div
              className="bg-[#D32F2F] rounded-2xl p-6 md:p-12 text-center shadow-xl relative overflow-hidden"
              whileHover={{ scale: 1.02 }}
              transition={{ type: "spring", stiffness: 300 }}
            >
              {/* Animated background decoration */}
              <div className="absolute inset-0 opacity-10" aria-hidden="true">
                {[...Array(10)].map((_, i) => (
                  <motion.div
                    key={i}
                    className="absolute w-3 h-3 bg-white rounded-full"
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
                      delay: i * 0.3,
                      ease: "easeInOut",
                    }}
                  />
                ))}
              </div>
              <p className="text-white text-xl md:text-3xl leading-relaxed italic relative z-10">
                "India believes in coexistence, not cruelty. Let thousands of letters flood the Supreme Court as a reminder that justice must be humane."
              </p>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}