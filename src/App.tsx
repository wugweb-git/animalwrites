import { Hero } from "./components/Hero";
import { Navigation } from "./components/Navigation";
import { CountdownTimer } from "./components/CountdownTimer";
import { AboutMovement } from "./components/AboutMovement";
import { PetitionNote } from "./components/PetitionNote";
import { WhyThisMatters } from "./components/WhyThisMatters";
import { KeyFacts } from "./components/KeyFacts";
import { HowToJoin } from "./components/HowToJoin";
import { LetterTemplates } from "./components/LetterTemplates";
import { LocalSupport } from "./components/LocalSupport";
import { Resources } from "./components/Resources";
import { ImpactStats } from "./components/ImpactStats";
import { Testimonials } from "./components/Testimonials";
import { Supporters } from "./components/Supporters";
import { PressMedia } from "./components/PressMedia";
import { FAQ } from "./components/FAQ";
import { VolunteerSignup } from "./components/VolunteerSignup";
import { SocialShare } from "./components/SocialShare";
import { JoinMovement } from "./components/JoinMovement";
import { OurMessage } from "./components/OurMessage";
import { Footer } from "./components/Footer";
import { FloatingCTA } from "./components/FloatingCTA";

export default function App() {
  return (
    <div className="min-h-screen bg-white">
      {/* Skip to main content link for keyboard users */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#D32F2F] focus:text-white focus:rounded-md focus:shadow-lg"
      >
        Skip to main content
      </a>
      
      <Navigation />
      
      <main id="main-content" className="pt-16 md:pt-20">
        <Hero />
        <CountdownTimer />
        <AboutMovement />
        <PetitionNote />
        <WhyThisMatters />
        <KeyFacts />
        <HowToJoin />
        <LetterTemplates />
        <LocalSupport />
        <Resources />
        <ImpactStats />
        <Testimonials />
        <Supporters />
        <PressMedia />
        <FAQ />
        <VolunteerSignup />
        <SocialShare />
        <JoinMovement />
        <OurMessage />
        <Footer />
        <FloatingCTA />
      </main>
    </div>
  );
}