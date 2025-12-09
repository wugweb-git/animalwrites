import { useEffect, useState } from "react";
import { motion, AnimatePresence } from "motion/react";

interface ConfettiProps {
  trigger: boolean;
}

export function Confetti({ trigger }: ConfettiProps) {
  const [particles, setParticles] = useState<Array<{ id: number; x: number; y: number; color: string; rotation: number }>>([]);

  useEffect(() => {
    if (trigger) {
      const colors = [
        "rgb(43, 43, 43)",      // --primary
        "rgb(255, 248, 243)",   // --surface-warm
        "rgb(255, 190, 26)",    // --accent
        "rgb(217, 119, 6)",     // --accent-hover
        "rgb(120, 113, 108)"    // neutral gray
      ];
      const newParticles = Array.from({ length: 50 }, (_, i) => ({
        id: i,
        x: Math.random() * window.innerWidth,
        y: -20,
        color: colors[Math.floor(Math.random() * colors.length)],
        rotation: Math.random() * 360
      }));
      setParticles(newParticles);

      // Clear particles after animation
      setTimeout(() => setParticles([]), 3000);
    }
  }, [trigger]);

  return (
    <div className="fixed inset-0 pointer-events-none z-50 overflow-hidden">
      <AnimatePresence>
        {particles.map((particle) => (
          <motion.div
            key={particle.id}
            initial={{
              x: particle.x,
              y: particle.y,
              rotate: particle.rotation,
              scale: 0
            }}
            animate={{
              y: window.innerHeight + 100,
              x: particle.x + (Math.random() - 0.5) * 200,
              rotate: particle.rotation + 720,
              scale: [0, 1, 1, 0.8]
            }}
            exit={{ opacity: 0 }}
            transition={{
              duration: 2.5,
              ease: "easeOut"
            }}
            className="absolute w-3 h-3 rounded-sm"
            style={{ backgroundColor: particle.color }}
            aria-hidden="true"
          />
        ))}
      </AnimatePresence>
    </div>
  );
}