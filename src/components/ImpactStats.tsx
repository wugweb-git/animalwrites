import { Users, Mail, MapPin, Heart } from "lucide-react";
import { useEffect, useState } from "react";
import { motion } from "motion/react";

interface Stat {
  icon: any;
  value: string;
  label: string;
  color: string;
}

export function ImpactStats() {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
        }
      },
      { threshold: 0.1 }
    );

    const element = document.getElementById("impact-stats");
    if (element) {
      observer.observe(element);
    }

    return () => {
      if (element) {
        observer.unobserve(element);
      }
    };
  }, []);

  const stats: Stat[] = [
    {
      icon: Users,
      value: "10,000+",
      label: "Citizens Committed",
      color: "text-[#D32F2F]",
    },
    {
      icon: MapPin,
      value: "100+",
      label: "Cities Participating",
      color: "text-[#D32F2F]",
    },
    {
      icon: Mail,
      value: "∞",
      label: "Letters of Hope",
      color: "text-[#D32F2F]",
    },
    {
      icon: Heart,
      value: "1",
      label: "United Movement",
      color: "text-[#D32F2F]",
    },
  ];

  return (
    <section
      id="impact-stats"
      className="py-16 md:py-20 bg-white"
      aria-labelledby="impact-stats-heading"
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
            <h2 id="impact-stats-heading" className="text-[#D32F2F] text-2xl md:text-4xl mb-4">
              Our Growing Movement
            </h2>
            <p className="text-[#666666] text-base md:text-lg">
              Join thousands of compassionate citizens making their voices heard
            </p>
          </motion.div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            {stats.map((stat, index) => (
              <motion.div
                key={index}
                initial={{ scale: 0.5, opacity: 0 }}
                animate={isVisible ? { scale: 1, opacity: 1 } : { scale: 0.5, opacity: 0 }}
                transition={{ delay: index * 0.1, duration: 0.5, type: "spring" }}
                whileHover={{ y: -10, scale: 1.05 }}
                className="bg-gradient-to-br from-[#FFF8F3] to-white rounded-2xl p-5 md:p-8 border-2 border-[#D32F2F]/20 hover:border-[#D32F2F] hover:shadow-xl transition-all text-center"
              >
                <motion.div
                  className={`inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-full bg-white shadow-lg mb-4 ${stat.color}`}
                  whileHover={{ rotate: 360 }}
                  transition={{ duration: 0.6 }}
                >
                  <stat.icon className="w-7 h-7 md:w-8 md:h-8" aria-hidden="true" />
                </motion.div>
                <div className={`text-2xl md:text-4xl mb-2 ${stat.color}`}>
                  {stat.value}
                </div>
                <div className="text-[#666666] text-xs md:text-base">
                  {stat.label}
                </div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="mt-12 text-center"
          >
            <div className="inline-block bg-gradient-to-r from-[#FFF8F3] to-white rounded-xl p-5 md:p-6 border-2 border-[#D32F2F]/30">
              <p className="text-[#333333] text-base md:text-lg">
                <strong className="text-[#D32F2F]">Together, we are unstoppable.</strong> Every letter counts. Every voice matters.
              </p>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}