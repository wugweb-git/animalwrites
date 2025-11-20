import { AlertCircle, Building2, Heart, Scale, Mail, Users } from "lucide-react";
import { motion } from "motion/react";
import { ImageWithFallback } from "./figma/ImageWithFallback";

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

  const legalPoints = [
    {
      icon: Scale,
      text: "Constitutional right recognized in SP Gupta vs Union of India",
    },
    {
      icon: Mail,
      text: "Handwritten letters carry legal weight and seriousness",
    },
    {
      icon: Users,
      text: "Nationwide peaceful civic action creates powerful visibility",
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
          {/* Main Heading */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="why-matters-heading" className="text-3xl md:text-5xl text-[#333333] mb-4 md:mb-6 font-bold">
              Why This Matters
            </h2>
            <p className="text-lg md:text-xl text-[#666666] max-w-3xl mx-auto">
              This is not a protest — it's a peaceful national letter movement for justice and empathy.
            </p>
          </motion.div>

          {/* Three Key Reasons */}
          <div className="grid sm:grid-cols-2 md:grid-cols-3 gap-6 md:gap-8 mb-12 md:mb-16">
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
                    <reason.icon className="w-7 h-7 md:w-8 md:h-8 text-[#F59E0B]" aria-hidden="true" />
                  </motion.div>
                  <h3 className="text-lg md:text-xl text-[#333333] mb-2 md:mb-3 text-center sm:text-left">{reason.title}</h3>
                  <p className="text-sm md:text-base text-[#666666] leading-relaxed text-center sm:text-left">{reason.description}</p>
                </motion.div>
              </motion.div>
            ))}
          </div>

          {/* Simplified Legal Foundation */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="mb-12 md:mb-16"
          >
            <motion.div
              className="bg-white rounded-2xl p-8 md:p-12 border-2 border-[#F59E0B]/20 shadow-xl"
              whileHover={{ borderColor: "rgba(245, 158, 11, 0.4)" }}
              transition={{ duration: 0.3 }}
            >
              <div className="text-center mb-8">
                <motion.div
                  className="inline-flex items-center gap-2 bg-[#F59E0B] px-5 py-2 rounded-full mb-6"
                  whileHover={{ scale: 1.05 }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  <Scale className="w-5 h-5 text-white" aria-hidden="true" />
                  <span className="text-white">Your Constitutional Right</span>
                </motion.div>
                <h3 className="text-2xl md:text-3xl text-[#333333] mb-4 font-bold">
                  Why Letters to the Supreme Court Work?
                </h3>
                <p className="text-base md:text-lg text-[#666666] max-w-2xl mx-auto">
                  The Supreme Court can take cognizance based on citizen letters — this is a rare and powerful constitutional tool available to every Indian.
                </p>
              </div>

              <div className="grid md:grid-cols-3 gap-6 md:gap-8 mb-8">
                {legalPoints.map((point, index) => (
                  <motion.div
                    key={index}
                    initial={{ opacity: 0, y: 10 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, delay: index * 0.1 }}
                    viewport={{ once: true }}
                    className="flex flex-col items-center text-center"
                  >
                    <motion.div
                      className="bg-[#FFF8F3] rounded-full w-16 h-16 flex items-center justify-center mb-4"
                      whileHover={{ rotate: 360, scale: 1.1 }}
                      transition={{ duration: 0.6 }}
                    >
                      <point.icon className="w-8 h-8 text-[#F59E0B]" aria-hidden="true" />
                    </motion.div>
                    <p className="text-sm md:text-base text-[#666666] leading-relaxed">{point.text}</p>
                  </motion.div>
                ))}
              </div>

              <div className="bg-[#FFF8F3] rounded-xl p-6 md:p-8 border-l-4 border-[#F59E0B]">
                <p className="text-center text-base md:text-lg text-[#333333]">
                  Use the uniform title: <strong className="text-[#F59E0B]">"IN RE: Compassion, Kindness & Humanity"</strong> to help the Court consolidate all letters into one national plea.
                </p>
              </div>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}