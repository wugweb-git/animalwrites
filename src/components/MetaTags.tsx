import { useEffect } from "react";

export function MetaTags() {
  useEffect(() => {
    // Set document title
    document.title = "Animal Writes - India's Largest Letter Petition Campaign for Street Dogs";

    // Create or update meta tags
    const setMetaTag = (name: string, content: string, property?: boolean) => {
      const attribute = property ? "property" : "name";
      let meta = document.querySelector(`meta[${attribute}="${name}"]`) as HTMLMetaElement;
      
      if (!meta) {
        meta = document.createElement("meta");
        meta.setAttribute(attribute, name);
        document.head.appendChild(meta);
      }
      
      meta.content = content;
    };

    // Basic meta tags
    setMetaTag("description", "Join India's largest citizen-led letter petition movement. Thousands will walk to GPOs on November 29, 2025, to post letters to the Supreme Court advocating for humane coexistence with street dogs.");
    setMetaTag("keywords", "animal rights, street dogs, India, petition, Supreme Court, animal welfare, coexistence, letter campaign, compassion");
    setMetaTag("author", "Animal Writes Movement");
    setMetaTag("viewport", "width=device-width, initial-scale=1.0");
    
    // Open Graph tags for social sharing
    setMetaTag("og:title", "Animal Writes - India's Largest Letter Petition Campaign", true);
    setMetaTag("og:description", "Join thousands across India on Nov 29, 2025. Write to the Supreme Court for humane coexistence with street dogs. #SaveIndianStreetDogs", true);
    setMetaTag("og:type", "website", true);
    setMetaTag("og:url", window.location.href, true);
    setMetaTag("og:site_name", "Animal Writes", true);
    setMetaTag("og:locale", "en_IN", true);
    
    // Twitter Card tags
    setMetaTag("twitter:card", "summary_large_image");
    setMetaTag("twitter:title", "Animal Writes - India's Largest Letter Petition Campaign");
    setMetaTag("twitter:description", "Join thousands across India on Nov 29, 2025. Write for coexistence, not cruelty. #SaveIndianStreetDogs #AnimalWrites");
    
    // Theme color
    setMetaTag("theme-color", "#D32F2F");
    
    // Mobile app capable
    setMetaTag("mobile-web-app-capable", "yes");
    setMetaTag("apple-mobile-web-app-capable", "yes");
    setMetaTag("apple-mobile-web-app-status-bar-style", "default");
    setMetaTag("apple-mobile-web-app-title", "Animal Writes");

  }, []);

  return null; // This component doesn't render anything
}
