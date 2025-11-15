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
      color: "text-[#333333]",
    },
    {
      icon: MapPin,
      value: "100+",
      label: "Cities Participating",
      color: "text-[#333333]",
    },
    {
      icon: Mail,
      value: "∞",
      label: "Letters of Hope",
      color: "text-[#333333]",
    },
    {
      icon: Heart,
      value: "1",
      label: "United Movement",
      color: "text-[#333333]",
    },
  ];

  return (
    <section
      className="py-16 md:py-24 bg-white"
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
            <h2 id="impact-stats-heading" className="text-[#F59E0B] text-2xl md:text-4xl mb-4 font-bold">
              Our Growing Movement
            </h2>
            <p className="text-[#666666] text-base md:text-lg">
              Join thousands of compassionate citizens making their voices heard
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="mt-12 text-center"
          >
            <div className="inline-block bg-gradient-to-r from-[#FFF8F3] to-white rounded-xl p-5 md:p-6 border-2 border-[#F59E0B]/30">
              <p className="text-[#333333] text-base md:text-lg">
                <strong className="text-[#F59E0B]">Together, we are unstoppable.</strong> Every letter counts. Every voice matters.
              </p>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}