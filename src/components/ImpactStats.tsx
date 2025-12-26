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
      color: "text-primary",
    },
    {
      icon: MapPin,
      value: "100+",
      label: "Cities Participating",
      color: "text-primary",
    },
    {
      icon: Mail,
      value: "∞",
      label: "Letters of Hope",
      color: "text-primary",
    },
    {
      icon: Heart,
      value: "1",
      label: "United Movement",
      color: "text-primary",
    },
  ];

  return (
    <section
      className="py-16 md:py-24 bg-background"
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
            <h2 className="mb-2 text-primary">
              Our Growing <span className="text-foreground" style={{ fontSize: 'inherit', fontWeight: 'inherit', lineHeight: 'inherit' }}>Movement</span>
            </h2>
            <p className="text-muted-foreground">
              Join thousands of compassionate citizens making their voices heard
            </p>
          </motion.div>

          {/* Stats Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8 mb-12">
            {stats.map((stat, index) => {
              const Icon = stat.icon;
              return (
                <motion.div
                  key={index}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.5, delay: index * 0.1 }}
                  viewport={{ once: true }}
                  className="flex flex-col items-center justify-center p-6 bg-gradient-to-b from-[var(--surface-warm)] to-background rounded-[var(--radius-xl)] border-2 border-accent/20 hover:border-accent transition-all hover:shadow-[var(--elevation-lg)]"
                >
                  <motion.div
                    whileHover={{ scale: 1.1, rotate: 5 }}
                    transition={{ duration: 0.3 }}
                  >
                    <Icon className={`w-10 h-10 md:w-12 md:h-12 ${stat.color} mb-4`} aria-hidden="true" />
                  </motion.div>
                  <p className="text-3xl md:text-4xl text-primary mb-2">
                    {stat.value}
                  </p>
                  <p className="text-sm md:text-base text-muted-foreground text-center">
                    {stat.label}
                  </p>
                </motion.div>
              );
            })}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="text-center"
          >
            <div className="inline-block bg-gradient-to-r from-[var(--surface-warm)] to-background rounded-[var(--radius-xl)] p-5 md:p-6 border-2 border-accent/30">
              <p className="text-primary text-base md:text-lg">
                <strong className="text-primary">Together, we are unstoppable.</strong> Every letter counts. Every voice matters.
              </p>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}