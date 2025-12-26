import { motion } from "motion/react";
import { Mail, Newspaper, Users, Heart, Target, CheckCircle2, TrendingUp, Vote } from "lucide-react";

export function WhatHappensNext() {
  const timelineSteps = [
    {
      icon: CheckCircle2,
      title: "2.5 Lakh Letters Delivered - Mission Accomplished! ✅",
      description: "Historic achievement: Over 2,50,000 handwritten letters successfully delivered to the Supreme Court from citizens across India.",
      status: "completed",
    },
    {
      icon: Mail,
      title: "Nationwide GPO Participation on November 29th",
      description: "Thousands of citizens walked to their nearest General Post Offices across India to hand-deliver letters urging reconsideration of stray dog removal orders.",
      status: "completed",
    },
    {
      icon: Newspaper,
      title: "Extensive Media Coverage Achieved",
      description: "Nationwide and regional media coverage highlighted the unprecedented citizen participation and the movement's impact on animal welfare advocacy.",
      status: "completed",
    },
    {
      icon: Users,
      title: "Grassroots Volunteer Network Activated",
      description: "Successfully mobilized thousands of volunteers across cities who coordinated local awareness campaigns, letter-writing drives, and GPO events.",
      status: "completed",
    },
    {
      icon: Heart,
      title: "Raised National Awareness on Humane Solutions",
      description: "Educated millions about Animal Birth Control (ABC) programs and humane alternatives to stray dog relocation through social media and on-ground campaigns.",
      status: "completed",
    },
    {
      icon: TrendingUp,
      title: "Record-Breaking Digital Engagement",
      description: "Over 2 lakh website visitors, massive social media reach, and thousands of downloaded letter templates demonstrating unprecedented online mobilization.",
      status: "completed",
    },
  ];

  return (
    <section id="what-happens-next" className="py-16 md:py-24 bg-gradient-to-b from-card to-[var(--surface-warm)]" aria-labelledby="what-happens-next-heading">
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="what-happens-next-heading" className="text-primary mb-4">
              Our Impact & What's Next
            </h2>
            <p className="text-muted-foreground max-w-3xl mx-auto">
              November 29th was a historic success! Here's what we achieved and how we're building on this momentum for lasting change.
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
                className={`flex gap-4 md:gap-6 items-start p-5 md:p-6 rounded-[var(--radius-xl)] border-2 transition-all ${
                  step.status === "completed"
                    ? "bg-green-50/50 border-green-200 hover:border-green-300"
                    : step.status === "current"
                    ? "bg-[var(--surface-warm)] border-accent hover:border-[var(--accent-hover)] shadow-[var(--elevation-lg)]"
                    : "bg-card border-accent/30 hover:border-accent/50"
                }`}
              >
                <motion.div
                  className={`flex-shrink-0 rounded-[var(--radius-full)] w-10 h-10 md:w-12 md:h-12 flex items-center justify-center shadow-[var(--elevation-lg)] ${
                    step.status === "completed"
                      ? "bg-green-500 text-white"
                      : step.status === "current"
                      ? "bg-accent text-white"
                      : "bg-primary text-white"
                  }`}
                  whileHover={{ scale: 1.1, rotate: 360 }}
                  transition={{ duration: 0.6 }}
                >
                  <step.icon className="w-5 h-5 md:w-6 md:h-6" aria-hidden="true" />
                </motion.div>
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-2">
                    <h3 className="text-primary">{step.title}</h3>
                    {step.status === "current" && (
                      <span className="px-2 py-1 bg-accent text-white rounded-[var(--radius-full)] animate-pulse" style={{ fontSize: '12px' }}>
                        LIVE
                      </span>
                    )}
                  </div>
                  <p className="text-muted-foreground leading-relaxed">{step.description}</p>
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
            className="mt-8 text-center bg-gradient-to-r from-accent to-[var(--accent-hover)] rounded-[var(--radius-2xl)] p-8 md:p-10 shadow-[var(--elevation-xl)]"
          >
            <h3 className="text-white mb-3">
              Join Phase 2.0: Pashu Bachao Vote Badhao! 🗳️
            </h3>
            <p className="text-white/90 mb-4">
              The movement continues! Register as a volunteer and help make animal welfare a priority in India's democratic process.
            </p>
            <p className="text-white/80">
              <strong>Stay Connected:</strong> Follow us on Facebook and Instagram for updates, resources, and ways to get involved.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}