import { useEffect, useState } from "react";
import { Calendar, Clock } from "lucide-react";
import { motion } from "framer-motion";

interface TimeLeft {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
}

export function CountdownTimer() {
  const [timeLeft, setTimeLeft] = useState<TimeLeft>({
    days: 0,
    hours: 0,
    minutes: 0,
    seconds: 0,
  });

  useEffect(() => {
    const targetDate = new Date("2025-11-29T00:00:00").getTime();

    const updateTimer = () => {
      const now = new Date().getTime();
      const difference = targetDate - now;

      if (difference > 0) {
        setTimeLeft({
          days: Math.floor(difference / (1000 * 60 * 60 * 24)),
          hours: Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
          minutes: Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60)),
          seconds: Math.floor((difference % (1000 * 60)) / 1000),
        });
      } else {
        setTimeLeft({ days: 0, hours: 0, minutes: 0, seconds: 0 });
      }
    };

    updateTimer();
    const interval = setInterval(updateTimer, 1000);

    return () => clearInterval(interval);
  }, []);

  const timeUnits = [
    { label: "Days", value: timeLeft.days, ariaLabel: `${timeLeft.days} days` },
    { label: "Hours", value: timeLeft.hours, ariaLabel: `${timeLeft.hours} hours` },
    { label: "Minutes", value: timeLeft.minutes, ariaLabel: `${timeLeft.minutes} minutes` },
    { label: "Seconds", value: timeLeft.seconds, ariaLabel: `${timeLeft.seconds} seconds` },
  ];

  return (
    <section 
      className="py-12 md:py-20 bg-[#D32F2F] relative overflow-hidden"
      aria-labelledby="countdown-heading"
    >
      {/* Animated background pattern */}
      <div className="absolute inset-0 opacity-10" aria-hidden="true">
        <div className="absolute top-0 left-0 w-full h-full">
          {[...Array(20)].map((_, i) => (
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
                delay: i * 0.2,
                ease: "easeInOut",
              }}
            />
          ))}
        </div>
      </div>

      <div className="container mx-auto px-4 relative z-10">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-8 md:mb-10"
          >
            <div className="inline-flex items-center gap-3 mb-4">
              <motion.div
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Calendar className="w-6 h-6 md:w-7 md:h-7 text-white" aria-hidden="true" />
              </motion.div>
              <h2 id="countdown-heading" className="text-white text-2xl md:text-4xl">
                Time Until Campaign Day
              </h2>
            </div>
            <p className="text-white/90 text-base md:text-lg">
              Every second counts. Prepare your letter today.
            </p>
          </motion.div>

          <div 
            className="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6 mb-8"
            role="timer"
            aria-live="polite"
            aria-atomic="true"
          >
            {timeUnits.map((unit, index) => (
              <motion.div
                key={unit.label}
                initial={{ opacity: 0, scale: 0.8 }}
                whileInView={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                viewport={{ once: true }}
                className="relative"
              >
                <motion.div
                  className="bg-white rounded-xl p-4 md:p-8 shadow-lg"
                  whileHover={{ 
                    scale: 1.05, 
                    boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)"
                  }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  <div className="flex flex-col items-center">
                    <motion.div
                      key={unit.value}
                      initial={{ y: -20, opacity: 0 }}
                      animate={{ y: 0, opacity: 1 }}
                      transition={{ duration: 0.3 }}
                      className="text-4xl md:text-6xl text-[#D32F2F] mb-1 md:mb-2 tabular-nums"
                      aria-label={unit.ariaLabel}
                    >
                      {unit.value.toString().padStart(2, "0")}
                    </motion.div>
                    <div className="text-[#666666] text-xs md:text-sm uppercase tracking-wider">
                      {unit.label}
                    </div>
                  </div>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="text-center"
          >
            <motion.div
              className="inline-flex items-center gap-2 md:gap-3 bg-white rounded-full px-4 md:px-6 py-2 md:py-3 shadow-lg"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 1.5, repeat: Infinity, ease: "easeInOut" }}
              >
                <Clock className="w-4 h-4 md:w-5 md:h-5 text-[#D32F2F]" aria-hidden="true" />
              </motion.div>
              <span className="text-[#333333] text-sm md:text-base">
                <span className="hidden sm:inline">29 November 2025 • All GPOs Across India</span>
                <span className="sm:hidden">29 Nov 2025 • All GPOs</span>
              </span>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}