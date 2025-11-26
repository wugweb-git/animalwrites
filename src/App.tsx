import { Hero } from "./components/Hero";
import { Navigation } from "./components/Navigation";
import { CountdownTimer } from "./components/CountdownTimer";
import { AboutMovement } from "./components/AboutMovement";
import { WhyThisMatters } from "./components/WhyThisMatters";
import { KeyFacts } from "./components/KeyFacts";
import { HowToJoin } from "./components/HowToJoin";
import { LetterTemplates } from "./components/LetterTemplates";
import { LocalSupport } from "./components/LocalSupport";
import { Resources } from "./components/Resources";
import { ImpactStats } from "./components/ImpactStats";
import { Testimonials } from "./components/Testimonials";
import { Supporters } from "./components/Supporters";
import { CampaignGallery } from "./components/CampaignGallery";
import { PressMedia } from "./components/PressMedia";
import { FAQ } from "./components/FAQ";
import { VolunteerSignup } from "./components/VolunteerSignup";
import { SocialShare } from "./components/SocialShare";
import { OurMessage } from "./components/OurMessage";
import { Footer } from "./components/Footer";
import { FloatingCTA } from "./components/FloatingCTA";
import { ScrollProgress } from "./components/ScrollProgress";
import { Confetti } from "./components/Confetti";
import { MetaTags } from "./components/MetaTags";
import { Timeline } from "./components/Timeline";
import { BackToTop } from "./components/BackToTop";
import { WhatHappensNext } from "./components/WhatHappensNext";
import { VisualImpact } from "./components/VisualImpact";
import { VolunteerAdmin } from "./components/VolunteerAdmin";

export default function App() {
  // Check if admin page should be shown
  const urlParams = new URLSearchParams(window.location.search);
  const isAdminPage = urlParams.get('admin') === 'volunteers';

  // Show admin page if requested
  if (isAdminPage) {
    return (
      <div className="min-h-screen bg-white">
        <MetaTags />
        <VolunteerAdmin />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <MetaTags />
      
      {/* Skip to main content link for keyboard users */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#333333] focus:text-white focus:rounded-md focus:shadow-lg"
      >
        Skip to main content
      </a>
      
      {/* Scroll progress indicator */}
      <ScrollProgress />
      
      <Navigation />
      
      <main id="main-content" className="pt-16 md:pt-20">
        {/* Hero & Awareness */}
        <Hero />
        <CountdownTimer />
        <AboutMovement />
        
        {/* Why & Context */}
        <WhyThisMatters />
        <VisualImpact />
        <ImpactStats />
        <Timeline />
        
        {/* How to Participate */}
        <HowToJoin />
        <LetterTemplates />
        <LocalSupport />
        <FAQ />
        
        {/* Get Involved */}
        <VolunteerSignup />
        
        {/* Social Proof & Community */}
        <Testimonials />
        <Supporters />
        <CampaignGallery />
        <PressMedia />
        
        {/* Resources & Next Steps */}
        <Resources />
        <WhatHappensNext />
        <SocialShare />
        <OurMessage />
        
        {/* Footer & Utils */}
        <Footer />
        <FloatingCTA />
        <BackToTop />
      </main>
    </div>
  );
}