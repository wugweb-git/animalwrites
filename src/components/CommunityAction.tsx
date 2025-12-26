import { Share2, Facebook, MessageCircle, Copy, Check, Users, Instagram, Heart, Quote } from "lucide-react";
import { Button } from "./ui/button";
import { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import svgQuotePaths from "../imports/svg-u67olcqmrd";

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
        paddingTop: '96px',
        paddingBottom: '96px'
      }}
      aria-labelledby="community-action-heading"
    >
      <div className="container mx-auto px-4">
        
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          viewport={{ once: true }}
          className="text-center mb-12 md:mb-16"
        >
          <h2 
            id="community-action-heading" 
            className="mb-4" 
            style={{ color: 'var(--foreground)' }}
          >
            Be the Voice for the Voiceless
          </h2>
          <p style={{ color: 'var(--muted-foreground)', maxWidth: '672px', margin: '0 auto' }}>
            Join thousands of citizens uniting for animal rights. Share, follow, and pledge your support today.
          </p>
        </motion.div>

        {/* Gandhi Quote Section with Image */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.1 }}
          viewport={{ once: true }}
          className="mb-12 md:mb-16"
        >
          <div 
            className="relative overflow-hidden mx-auto hidden md:block"
            style={{
              maxWidth: '1095px',
              height: '355px',
              borderRadius: 'var(--radius-xl)',
              boxShadow: 'var(--elevation-lg)',
              background: 'linear-gradient(135deg, #FFF5EB 0%, #FFE4C4 30%, #FFDAB3 60%, #FFD4A3 100%)'
            }}
          >
            
            {/* Gandhi Image positioned on the right */}
            <div className="absolute right-0 bottom-0 h-full flex items-end justify-end">
              <img
                src="https://wugweb.online/gandhi.png"
                alt="Mahatma Gandhi"
                className="object-cover object-bottom"
                style={{ 
                  height: '100%',
                  width: 'auto',
                  minWidth: '300px',
                  maxWidth: '450px'
                }}
              />
            </div>

            {/* Quote Content - Left Side */}
            <div 
              className="absolute left-0 top-0 h-full flex flex-col justify-center"
              style={{ 
                padding: '90px 80px',
                maxWidth: '607px'
              }}
            >
              {/* SVG Quotation Mark */}
              <motion.div
                initial={{ scale: 0 }}
                whileInView={{ scale: 1 }}
                transition={{ duration: 0.5, delay: 0.2 }}
                viewport={{ once: true }}
                className="mb-4"
                style={{ width: '46px', height: '35px' }}
              >
                <svg 
                  className="block size-full" 
                  fill="none" 
                  preserveAspectRatio="none" 
                  viewBox="0 0 46.34 35"
                  aria-hidden="true"
                >
                  <path 
                    d={svgQuotePaths.p95c4200} 
                    fill="var(--accent)" 
                  />
                </svg>
              </motion.div>
              
              {/* Quote Text */}
              <blockquote 
                className="italic mb-6"
                style={{ 
                  fontSize: '24px',
                  lineHeight: 'normal',
                  fontWeight: 'var(--font-weight-semibold)',
                  color: '#141414'
                }}
              >
                The greatness of a nation and its moral progress can be judged by the way its animals are treated.
              </blockquote>
              
              {/* Attribution */}
              <cite 
                className="not-italic" 
                style={{ 
                  color: 'black',
                  fontWeight: 'var(--font-weight-medium)',
                  fontSize: '16px'
                }}
              >
                — Mahatma Gandhi
              </cite>
            </div>
          </div>

          {/* Mobile Version - Stacked */}
          <div 
            className="relative overflow-hidden mx-auto md:hidden"
            style={{
              borderRadius: 'var(--radius-xl)',
              boxShadow: 'var(--elevation-lg)',
              background: 'linear-gradient(135deg, #FFF5EB 0%, #FFE4C4 50%, #FFDAB3 100%)'
            }}
          >
            {/* Quote Content */}
            <div className="p-8">
              {/* SVG Quotation Mark */}
              <motion.div
                initial={{ scale: 0 }}
                whileInView={{ scale: 1 }}
                transition={{ duration: 0.5, delay: 0.2 }}
                viewport={{ once: true }}
                className="mb-4"
                style={{ width: '36px', height: '27px' }}
              >
                <svg 
                  className="block size-full" 
                  fill="none" 
                  preserveAspectRatio="none" 
                  viewBox="0 0 46.34 35"
                  aria-hidden="true"
                >
                  <path 
                    d={svgQuotePaths.p95c4200} 
                    fill="var(--accent)" 
                  />
                </svg>
              </motion.div>
              
              {/* Quote Text */}
              <blockquote 
                className="italic mb-6"
                style={{ 
                  fontSize: '20px',
                  lineHeight: '1.4',
                  fontWeight: 'var(--font-weight-semibold)',
                  color: '#141414'
                }}
              >
                The greatness of a nation and its moral progress can be judged by the way its animals are treated.
              </blockquote>
              
              {/* Attribution */}
              <cite 
                className="not-italic" 
                style={{ 
                  color: 'black',
                  fontWeight: 'var(--font-weight-medium)',
                  fontSize: '16px'
                }}
              >
                — Mahatma Gandhi
              </cite>
            </div>

            {/* Gandhi Image */}
            <div className="flex justify-center pb-0">
              <img
                src="https://wugweb.online/gandhi.png"
                alt="Mahatma Gandhi"
                className="w-auto object-contain"
                style={{ maxHeight: '280px' }}
              />
            </div>
          </div>
        </motion.div>

        {/* Instructional Text */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.2 }}
          viewport={{ once: true }}
          className="text-center mb-8"
          style={{ maxWidth: '1095px', margin: '0 auto 32px' }}
        >
          <p style={{ color: 'var(--muted-foreground)', lineHeight: '1.5' }}>
            Help us reach every corner of India. Share this campaign with your friends, family, and community.<br />
            Follow the official campaign page for updates, resources, and to connect with thousands of compassionate citizens across India
          </p>
        </motion.div>

        {/* Hashtags */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.3 }}
          viewport={{ once: true }}
          className="flex flex-wrap justify-center gap-2 mb-12 md:mb-16"
        >
          {['#SaveIndianStreetDogs', '#NoDogsNoVotes', '#AnimalWrites'].map((tag, index) => (
            <motion.span
              key={tag}
              initial={{ scale: 0.8, opacity: 0 }}
              whileInView={{ scale: 1, opacity: 1 }}
              transition={{ duration: 0.3, delay: 0.4 + index * 0.1 }}
              viewport={{ once: true }}
              whileHover={{ scale: 1.05 }}
              className="inline-block"
              style={{
                backgroundColor: 'white',
                border: '1px solid rgba(255, 165, 0, 0.3)',
                borderRadius: 'var(--radius-full)',
                padding: '6px 20px',
                color: 'var(--accent)',
                fontWeight: 'var(--font-weight-regular)',
                fontSize: '16px',
                boxShadow: 'var(--elevation-sm)'
              }}
            >
              {tag}
            </motion.span>
          ))}
        </motion.div>

        {/* Bottom CTA Section */}
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.5 }}
          viewport={{ once: true }}
          className="flex flex-col lg:flex-row items-center justify-between gap-6 lg:gap-8"
          style={{ 
            maxWidth: '967px', 
            margin: '0 auto',
            padding: '16px 0'
          }}
        >
          {/* Left: Join the movement text */}
          <div className="text-center lg:text-left">
            <h3 style={{ fontSize: 'clamp(24px, 4vw, 32px)', lineHeight: '1.25' }}>
              <span style={{ color: 'var(--foreground)', fontWeight: 'var(--font-weight-medium)' }}>Join the </span>
              <span style={{ color: 'var(--accent)', fontWeight: 'var(--font-weight-bold)' }}>movement now</span>
            </h3>
          </div>

          {/* Right: Social icons and WhatsApp button */}
          <div className="flex flex-col sm:flex-row items-center gap-4 sm:gap-5">
            {/* Social Icons */}
            <div className="flex items-center gap-5">
              <p style={{ color: 'var(--foreground)', fontSize: '16px', fontWeight: 'var(--font-weight-regular)' }}>
                Follow / Share us on
              </p>
              <div className="flex items-center gap-4">
                {/* Facebook */}
                <motion.a
                  href="https://www.facebook.com/sharer/sharer.php?u=https://animalwrites.in"
                  target="_blank"
                  rel="noopener noreferrer"
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  aria-label="Share on Facebook"
                  className="inline-block"
                >
                  <div 
                    className="w-6 h-6 rounded-full flex items-center justify-center"
                    style={{ backgroundColor: '#1877F2' }}
                  >
                    <Facebook className="w-4 h-4" style={{ color: 'white' }} aria-hidden="true" />
                  </div>
                </motion.a>

                {/* WhatsApp */}
                <motion.a
                  href={`https://wa.me/?text=${encodeURIComponent(shareText + ' ' + shareUrl)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  aria-label="Share on WhatsApp"
                  className="inline-block"
                >
                  <div 
                    className="w-6 h-6 rounded-full flex items-center justify-center"
                    style={{ background: 'linear-gradient(to top, #20B038, #60D66A)' }}
                  >
                    <MessageCircle className="w-4 h-4" style={{ color: 'white' }} aria-hidden="true" />
                  </div>
                </motion.a>

                {/* X (Twitter) */}
                <motion.a
                  href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  aria-label="Share on X"
                  className="inline-block"
                >
                  <div 
                    className="w-6 h-6 rounded-full flex items-center justify-center"
                    style={{ backgroundColor: 'black' }}
                  >
                    <Share2 className="w-3 h-3" style={{ color: 'white' }} aria-hidden="true" />
                  </div>
                </motion.a>

                {/* Instagram */}
                <motion.a
                  href="https://www.instagram.com/animalwrites_india/"
                  target="_blank"
                  rel="noopener noreferrer"
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  aria-label="Follow on Instagram"
                  className="inline-block"
                >
                  <div 
                    className="w-6 h-6 rounded-full flex items-center justify-center overflow-hidden"
                    style={{ 
                      background: 'linear-gradient(45deg, #FEE411 0%, #FEDB16 5%, #FEC125 13%, #FE983D 24%, #FE5F5E 37%, #FE2181 50%, #9000DC 100%)'
                    }}
                  >
                    <Instagram className="w-4 h-4" style={{ color: 'white' }} aria-hidden="true" />
                  </div>
                </motion.a>
              </div>
            </div>

            {/* WhatsApp Group Button */}
            <motion.a
              href="https://chat.whatsapp.com/EzE1VZTszFoLDwqEOCClxR"
              target="_blank"
              rel="noopener noreferrer"
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              className="inline-flex items-center gap-3"
              style={{
                backgroundColor: '#25D366',
                color: 'white',
                padding: '12px 24px',
                borderRadius: 'var(--radius-md)',
                fontSize: '16px',
                fontWeight: 'var(--font-weight-medium)',
                boxShadow: 'var(--elevation-md)',
                textDecoration: 'none'
              }}
              aria-label="Join WhatsApp Group"
            >
              <MessageCircle className="w-5 h-5" aria-hidden="true" />
              Join WhatsApp Group
            </motion.a>
          </div>
        </motion.div>

      </div>
    </section>
  );
}