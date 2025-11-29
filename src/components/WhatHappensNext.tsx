import { motion } from "motion/react";
import { Calendar, Mail, Newspaper, Users, Heart, Target } from "lucide-react";

export function WhatHappensNext() {
  const nextSteps = [
    {
      icon: Calendar,
      title: "November 29, 2025 - The Big Day",
      description: "Thousands of citizens across India walk to their nearest post offices to mail letters to the Supreme Court.",
      color: "#F59E0B",
    },
    {
      icon: Mail,
      title: "Letter Delivery & Documentation",
      description: "All letters are tracked and documented. We'll create a public record of participation numbers city-wise.",
      color: "#F59E0B",
    },
    {
      icon: Newspaper,
      title: "Media Amplification",
      description: "National and regional media coverage highlighting citizen participation and the movement's scale.",
      color: "#F59E0B",
    },
    {
      icon: Users,
      title: "Follow-up Advocacy",
      description: "Coordination with animal welfare organizations and legal experts to track the Supreme Court's response.",
      color: "#F59E0B",
    },
    {
      icon: Heart,
      title: "Long-term Impact",
      description: "Building awareness about ABC programs, community involvement in street dog welfare, and sustainable coexistence models.",
      color: "#F59E0B",
    },
    {
      icon: Target,
      title: "Community Building",
      description: "Creating lasting networks of animal welfare volunteers and advocates across every city in India.",
      color: "#F59E0B",
    },
  ];

  const timelineSteps = [
    {
      icon: Mail,
      title: "Letter Delivery & Documentation",
      description: "All letters are tracked and documented. We'll create a public record of participation numbers city-wise.",
      color: "#F59E0B",
    },
    {
      icon: Newspaper,
      title: "Media Amplification",
      description: "National and regional media coverage highlighting citizen participation and the movement's scale.",
      color: "#F59E0B",
    },
    {
      icon: Users,
      title: "Follow-up Advocacy",
      description: "Coordination with animal welfare organizations and legal experts to track the Supreme Court's response.",
      color: "#F59E0B",
    },
    {
      icon: Heart,
      title: "Long-term Impact",
      description: "Building awareness about ABC programs, community involvement in street dog welfare, and sustainable coexistence models.",
      color: "#F59E0B",
    },
    {
      icon: Target,
      title: "Community Building",
      description: "Creating lasting networks of animal welfare volunteers and advocates across every city in India.",
      color: "#F59E0B",
    },
  ];

  return (
    <section id="what-happens-next" className="py-16 md:py-24 bg-gradient-to-b from-white to-[#FFF8F3]" aria-labelledby="what-happens-next-heading">
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="what-happens-next-heading" className="text-2xl md:text-4xl text-[#333333] mb-4 font-bold">
              What Happens After November 29th?
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-3xl mx-auto">
              This movement doesn't end with the letter delivery. Here's our roadmap for creating lasting change.
            </p>
          </motion.div>

          {/* Timeline */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.6 }}
            viewport={{ once: true }}
            className="space-y-6"
          >
            {timelineSteps.map((step, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, x: -20 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.5, delay: 0.7 + index * 0.1 }}
                viewport={{ once: true }}
                className="flex gap-4 md:gap-6 items-start"
              >
                <motion.div
                  className="flex-shrink-0 bg-[#333333] text-white rounded-full w-10 h-10 md:w-12 md:h-12 flex items-center justify-center shadow-lg"
                  whileHover={{ scale: 1.1, rotate: 360 }}
                  transition={{ duration: 0.6 }}
                >
                  <step.icon className="w-5 h-5 md:w-6 md:h-6" aria-hidden="true" />
                </motion.div>
                <div className="flex-1">
                  <h3 className="text-lg md:text-xl text-[#333333] mb-2">{step.title}</h3>
                  <p className="text-sm md:text-base text-[#666666] leading-relaxed">{step.description}</p>
                </div>
              </motion.div>
            ))}
          </motion.div>

          {/* CTA */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 1 }}
            viewport={{ once: true }}
            className="text-center bg-[#FFF8F3] rounded-2xl p-8 md:p-10 border-2 border-[#F59E0B]/20"
          >
            <p className="text-[#666666] text-sm md:text-base">
              <strong className="text-[#D97706]">Stay Connected:</strong> Follow us on Facebook for post-campaign updates and ongoing initiatives.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}