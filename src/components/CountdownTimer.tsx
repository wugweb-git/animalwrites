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
      className="py-6 md:py-8 bg-[#333333] relative overflow-hidden"
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

      <div className="container mx-auto px-6 md:px-12 lg:px-16 relative z-10">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6 md:gap-8">
            {/* Left side - Content */}
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
              viewport={{ once: true }}
              className="flex-shrink-0"
            >
              <div className="flex items-center gap-3 mb-2">
                <motion.div
                  animate={{ rotate: [0, 10, -10, 0] }}
                  transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                >
                  <Calendar className="w-5 h-5 md:w-6 md:h-6 text-white" aria-hidden="true" />
                </motion.div>
                <h2 id="countdown-heading" className="text-white text-xl md:text-2xl">
                  Time Until Campaign Day
                </h2>
              </div>
              <p className="text-white/90 text-sm md:text-base mb-2">
                Every second counts. Prepare your letter today.
              </p>
              <motion.div
                className="inline-flex items-center gap-2 bg-white/10 rounded-full px-3 md:px-4 py-1.5 md:py-2 backdrop-blur-sm border border-white/20"
                whileHover={{ scale: 1.05 }}
                transition={{ type: "spring", stiffness: 400 }}
              >
                <Clock className="w-3.5 h-3.5 md:w-4 md:h-4 text-white" aria-hidden="true" />
                <span className="text-white text-xs md:text-sm">
                  29 November 2025 • All GPOs Across India
                </span>
              </motion.div>
            </motion.div>

            {/* Right side - Countdown */}
            <div 
              className="flex gap-2 md:gap-4"
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
                >
                  <motion.div
                    className="bg-white rounded-lg p-3 md:p-4 shadow-lg min-w-[60px] md:min-w-[80px]"
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
                        className="text-2xl md:text-4xl text-[#F59E0B] mb-0.5 md:mb-1 tabular-nums"
                        aria-label={unit.ariaLabel}
                      >
                        {unit.value.toString().padStart(2, "0")}
                      </motion.div>
                      <div className="text-[#666666] text-[10px] md:text-xs uppercase tracking-wider">
                        {unit.label}
                      </div>
                    </div>
                  </motion.div>
                </motion.div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}