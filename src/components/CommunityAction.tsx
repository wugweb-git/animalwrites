import { Share2, Facebook, MessageCircle, Copy, Check, Users, Instagram, Heart, Quote } from "lucide-react";
import { Button } from "./ui/button";
import { useState } from "react";
import { motion, AnimatePresence } from "motion/react";

export function CommunityAction() {
  const [copied, setCopied] = useState(false);

  const shareText = "Join India's largest letter petition campaign for street dogs! Write to the Supreme Court on 29 Nov 2025. Every voice matters. #SaveIndianStreetDogs #AnimalWrites";
  const shareUrl = typeof window !== "undefined" ? window.location.href : "";

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(`${shareText}\n${shareUrl}`);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  };

  const shareOnFacebook = () => {
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
    window.open(url, "_blank");
  };

  const shareOnWhatsApp = () => {
    const url = `https://wa.me/?text=${encodeURIComponent(shareText + " " + shareUrl)}`;
    window.open(url, "_blank");
  };

  return (
    <section 
      style={{ 
        background: 'linear-gradient(to bottom, white, var(--surface-warm))',
        paddingTop: '80px',
        paddingBottom: '80px'
      }}
      aria-labelledby="community-action-heading"
    >
      <div className="container mx-auto px-4" style={{ maxWidth: '1000px' }}>
        
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7 }}
          viewport={{ once: true }}
          className="text-center mb-12"
        >
          <h2 
            id="community-action-heading" 
            className="mb-4" 
            style={{ color: 'var(--foreground)' }}
          >
            Be the Voice for the Voiceless
          </h2>
          <p style={{ color: 'var(--muted-foreground)' }}>
            Join thousands of citizens uniting for animal rights. Share, follow, and pledge your support today.
          </p>
        </motion.div>

        {/* Quote Section */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.1 }}
          viewport={{ once: true }}
          className="text-center mb-12"
        >
          <blockquote 
            className="italic mb-4"
            style={{ 
              fontSize: '28px',
              lineHeight: '1.5',
              fontWeight: 'var(--font-weight-medium)',
              color: 'var(--foreground)',
              maxWidth: '900px',
              margin: '0 auto 16px'
            }}
          >
            "The greatness of a nation and its moral progress can be judged by the way its animals are treated."
          </blockquote>
          <cite 
            className="not-italic" 
            style={{ 
              color: 'var(--accent)',
              fontWeight: 'var(--font-weight-bold)',
              fontSize: '18px'
            }}
          >
            — Mahatma Gandhi
          </cite>
        </motion.div>

        {/* Divider */}
        <div 
          style={{ 
            height: '1px', 
            background: 'var(--border)', 
            maxWidth: '120px', 
            margin: '48px auto' 
          }} 
        />

        {/* Instructional Text */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.2 }}
          viewport={{ once: true }}
          className="text-center mb-12"
          style={{ maxWidth: '800px', margin: '0 auto 48px' }}
        >
          <p style={{ color: 'var(--muted-foreground)', marginBottom: '12px' }}>
            Help us reach every corner of India. Share this campaign with your friends, family, and community.
          </p>
          <p style={{ color: 'var(--muted-foreground)', marginBottom: '24px' }}>
            Follow the official campaign page for updates, resources, and to connect with thousands of compassionate citizens across India
          </p>
          
          <div style={{ marginTop: '32px' }}>
            <p style={{ color: 'var(--foreground)', marginBottom: '8px' }}>
              Write your heart.
            </p>
            <p 
              style={{ 
                color: 'var(--accent)', 
                fontWeight: 'var(--font-weight-semibold)',
                fontSize: '18px',
                marginBottom: '8px'
              }}
            >
              Seal it with compassion.
            </p>
            <p style={{ color: 'var(--foreground)' }}>
              Post for justice.
            </p>
          </div>
        </motion.div>

        {/* Emoji Icons */}
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          whileInView={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.5, delay: 0.3 }}
          viewport={{ once: true }}
          className="flex justify-center gap-8 mb-10"
          style={{ fontSize: '32px' }}
        >
          <span aria-label="Dog" className="inline-block hover:scale-110 transition-transform">🐕</span>
          <span aria-label="Heart" className="inline-block hover:scale-110 transition-transform">❤️</span>
          <span aria-label="India" className="inline-block hover:scale-110 transition-transform">🇮🇳</span>
        </motion.div>

        {/* Hashtags */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.4 }}
          viewport={{ once: true }}
          className="text-center mb-16"
        >
          <div className="flex flex-wrap justify-center gap-4">
            <span 
              style={{
                color: 'var(--accent)',
                fontWeight: 'var(--font-weight-semibold)',
                fontSize: '16px'
              }}
            >
              #SaveIndianStreetDogs
            </span>
            <span 
              style={{
                color: 'var(--accent)',
                fontWeight: 'var(--font-weight-semibold)',
                fontSize: '16px'
              }}
            >
              #NoDogsNoVotes
            </span>
            <span 
              style={{
                color: 'var(--accent)',
                fontWeight: 'var(--font-weight-semibold)',
                fontSize: '16px'
              }}
            >
              #AnimalWrites
            </span>
          </div>
        </motion.div>

        {/* Action Buttons */}
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.5 }}
          viewport={{ once: true }}
          className="flex flex-wrap justify-center gap-3 items-center"
        >
          {/* Share on Facebook */}
          <Button
            onClick={shareOnFacebook}
            className="gap-2 group hover:scale-105 transition-all duration-200"
            style={{
              backgroundColor: '#1877F2',
              color: 'white',
              borderRadius: 'var(--radius-md)',
              padding: '14px 28px',
              boxShadow: 'var(--elevation-sm)'
            }}
            aria-label="Share on Facebook"
          >
            <Facebook className="w-5 h-5 group-hover:scale-110 transition-transform" aria-hidden="true" />
            <span>Share on Facebook</span>
          </Button>

          {/* Share on WhatsApp */}
          <Button
            onClick={shareOnWhatsApp}
            className="gap-2 group hover:scale-105 transition-all duration-200"
            style={{
              backgroundColor: '#25D366',
              color: 'white',
              borderRadius: 'var(--radius-md)',
              padding: '14px 28px',
              boxShadow: 'var(--elevation-sm)'
            }}
            aria-label="Share on WhatsApp"
          >
            <MessageCircle className="w-5 h-5 group-hover:scale-110 transition-transform" aria-hidden="true" />
            <span>Share on WhatsApp</span>
          </Button>

          {/* Instagram Link */}
          <Button
            asChild
            className="gap-2 group hover:scale-105 transition-all duration-200"
          >
            <a
              href="https://www.instagram.com/animalwrites_india/"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Follow Animal Writes India on Instagram"
              style={{
                backgroundColor: 'white',
                color: 'var(--foreground)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--radius-md)',
                padding: '14px 28px',
                boxShadow: 'var(--elevation-sm)',
                display: 'inline-flex',
                alignItems: 'center'
              }}
            >
              <Instagram className="w-5 h-5 group-hover:scale-110 transition-transform" aria-hidden="true" />
              <span>instagram.com/animalwrites_india</span>
            </a>
          </Button>

          {/* Share on X */}
          <Button
            asChild
            className="gap-2 group hover:scale-105 transition-all duration-200"
          >
            <a
              href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Share on X (Twitter)"
              style={{
                backgroundColor: 'white',
                color: 'var(--foreground)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--radius-md)',
                padding: '14px 28px',
                boxShadow: 'var(--elevation-sm)',
                display: 'inline-flex',
                alignItems: 'center'
              }}
            >
              <Share2 className="w-5 h-5 group-hover:scale-110 transition-transform" aria-hidden="true" />
              <span>share on X.com</span>
            </a>
          </Button>

          {/* Join WhatsApp Group */}
          <Button
            asChild
            className="gap-2 group hover:scale-105 transition-all duration-200"
          >
            <a
              href="https://chat.whatsapp.com/EzE1VZTszFoLDwqEOCClxR"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Join Animal Writes India WhatsApp Group"
              style={{
                backgroundColor: '#25D366',
                color: 'white',
                borderRadius: 'var(--radius-md)',
                padding: '14px 28px',
                boxShadow: 'var(--elevation-sm)',
                display: 'inline-flex',
                alignItems: 'center'
              }}
            >
              <MessageCircle className="w-5 h-5 group-hover:scale-110 transition-transform" aria-hidden="true" />
              <span>Join WhatsApp Group</span>
            </a>
          </Button>

          {/* Primary CTA - Join the Movement */}
          <Button
            className="gap-2 group hover:scale-105 transition-all duration-200"
            style={{
              backgroundColor: 'var(--accent)',
              color: 'white',
              borderRadius: 'var(--radius-md)',
              padding: '14px 36px',
              boxShadow: 'var(--elevation-md)',
              textTransform: 'uppercase',
              letterSpacing: '0.5px',
              fontWeight: 'var(--font-weight-semibold)'
            }}
            aria-label="Join the Movement"
          >
            <Heart className="w-5 h-5 fill-current group-hover:scale-110 transition-transform" aria-hidden="true" />
            <span>Join The Movement</span>
          </Button>
        </motion.div>

      </div>
    </section>
  );
}