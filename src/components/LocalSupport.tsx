import { useState } from "react";
import { MapPin, Search, Building2, Phone, Navigation, Users, ChevronDown, ChevronUp } from "lucide-react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { motion } from "motion/react";

interface GPOLocation {
  city: string;
  state: string;
  address: string;
  pincode: string;
  phone?: string;
  googleMapsLink: string;
}

export function LocalSupport() {
  const [searchTerm, setSearchTerm] = useState("");
  const [showAll, setShowAll] = useState(false);

  const gpoLocations: GPOLocation[] = [
    {
      city: "New Delhi",
      state: "Delhi",
      address: "Sansad Marg, Connaught Place",
      pincode: "110001",
      phone: "011-23363412",
      googleMapsLink: "https://maps.google.com/?q=GPO+New+Delhi",
    },
    {
      city: "Mumbai",
      state: "Maharashtra",
      address: "Walchand Hirachand Marg, Ballard Estate",
      pincode: "400001",
      phone: "022-22620970",
      googleMapsLink: "https://maps.google.com/?q=GPO+Mumbai",
    },
    {
      city: "Kolkata",
      state: "West Bengal",
      address: "BBD Bagh",
      pincode: "700001",
      phone: "033-22421122",
      googleMapsLink: "https://maps.google.com/?q=GPO+Kolkata",
    },
    {
      city: "Chennai",
      state: "Tamil Nadu",
      address: "Rajaji Salai, George Town",
      pincode: "600001",
      phone: "044-25256250",
      googleMapsLink: "https://maps.google.com/?q=GPO+Chennai",
    },
    {
      city: "Bangalore",
      state: "Karnataka",
      address: "Raj Bhavan Road, High Grounds",
      pincode: "560001",
      phone: "080-22867780",
      googleMapsLink: "https://maps.google.com/?q=GPO+Bangalore",
    },
    {
      city: "Hyderabad",
      state: "Telangana",
      address: "Abids Road",
      pincode: "500001",
      phone: "040-24752233",
      googleMapsLink: "https://maps.google.com/?q=GPO+Hyderabad",
    },
    {
      city: "Ahmedabad",
      state: "Gujarat",
      address: "Relief Road",
      pincode: "380001",
      phone: "079-25506602",
      googleMapsLink: "https://maps.google.com/?q=GPO+Ahmedabad",
    },
    {
      city: "Pune",
      state: "Maharashtra",
      address: "Shivajinagar",
      pincode: "411001",
      phone: "020-25534824",
      googleMapsLink: "https://maps.google.com/?q=GPO+Pune",
    },
    {
      city: "Jaipur",
      state: "Rajasthan",
      address: "MI Road",
      pincode: "302001",
      phone: "0141-2368219",
      googleMapsLink: "https://maps.google.com/?q=GPO+Jaipur",
    },
    {
      city: "Lucknow",
      state: "Uttar Pradesh",
      address: "Hazratganj",
      pincode: "226001",
      phone: "0522-2634121",
      googleMapsLink: "https://maps.google.com/?q=GPO+Lucknow",
    },
    {
      city: "Chandigarh",
      state: "Chandigarh",
      address: "Sector 17",
      pincode: "160017",
      phone: "0172-2704247",
      googleMapsLink: "https://maps.google.com/?q=GPO+Chandigarh",
    },
    {
      city: "Bhopal",
      state: "Madhya Pradesh",
      address: "TT Nagar",
      pincode: "462003",
      phone: "0755-2554033",
      googleMapsLink: "https://maps.google.com/?q=GPO+Bhopal",
    },
    {
      city: "Patna",
      state: "Bihar",
      address: "Buddha Marg",
      pincode: "800001",
      phone: "0612-2203854",
      googleMapsLink: "https://maps.google.com/?q=GPO+Patna",
    },
    {
      city: "Kochi",
      state: "Kerala",
      address: "MG Road",
      pincode: "682001",
      phone: "0484-2363073",
      googleMapsLink: "https://maps.google.com/?q=GPO+Kochi",
    },
    {
      city: "Thiruvananthapuram",
      state: "Kerala",
      address: "General Hospital Road",
      pincode: "695001",
      phone: "0471-2323271",
      googleMapsLink: "https://maps.google.com/?q=GPO+Thiruvananthapuram",
    },
    {
      city: "Guwahati",
      state: "Assam",
      address: "Panbazar",
      pincode: "781001",
      phone: "0361-2633601",
      googleMapsLink: "https://maps.google.com/?q=GPO+Guwahati",
    },
    {
      city: "Indore",
      state: "Madhya Pradesh",
      address: "MG Road",
      pincode: "452001",
      phone: "0731-2435042",
      googleMapsLink: "https://maps.google.com/?q=GPO+Indore",
    },
    {
      city: "Nagpur",
      state: "Maharashtra",
      address: "GPO Square, Sitabuldi",
      pincode: "440001",
      phone: "0712-2523515",
      googleMapsLink: "https://maps.google.com/?q=GPO+Nagpur",
    },
    {
      city: "Bhubaneswar",
      state: "Odisha",
      address: "Master Canteen Square",
      pincode: "751001",
      phone: "0674-2431026",
      googleMapsLink: "https://maps.google.com/?q=GPO+Bhubaneswar",
    },
    {
      city: "Ranchi",
      state: "Jharkhand",
      address: "Main Road",
      pincode: "834001",
      phone: "0651-2331111",
      googleMapsLink: "https://maps.google.com/?q=GPO+Ranchi",
    },
  ];

  const filteredGPOs = gpoLocations.filter(
    (location) =>
      location.city.toLowerCase().includes(searchTerm.toLowerCase()) ||
      location.state.toLowerCase().includes(searchTerm.toLowerCase()) ||
      location.pincode.includes(searchTerm)
  );

  // Show only 3 by default, unless searching or showAll is true
  const displayedGPOs = searchTerm || showAll ? filteredGPOs : filteredGPOs.slice(0, 3);
  const hasMore = filteredGPOs.length > 3;

  return (
    <section 
      id="local-support" 
      className="py-16 md:py-24 bg-gray-50"
      aria-labelledby="gpo-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-[#FFF8F3] px-4 py-2 rounded-full mb-4 border border-[#D32F2F]/20"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <MapPin className="w-5 h-5 text-[#D32F2F]" aria-hidden="true" />
              <span className="text-[#D32F2F]">Find Your GPO</span>
            </motion.div>
            <h2 id="gpo-heading" className="text-2xl md:text-4xl text-[#333333] mb-4">
              Find Your Nearest Post Office
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-2xl mx-auto">
              Search by city, state, or pincode to find your nearest post office
            </p>
          </motion.div>

          {/* Search Bar */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="mb-12"
          >
            <div className="relative max-w-2xl mx-auto">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" aria-hidden="true" />
              <Input
                type="search"
                placeholder="Search by city, state, or pincode..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-12 py-5 md:py-6 text-base md:text-lg border-2 border-gray-200 focus:border-[#D32F2F] rounded-xl transition-colors"
                aria-label="Search for post offices"
              />
            </div>
            {searchTerm && (
              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="text-center text-sm text-[#666666] mt-3"
                role="status"
                aria-live="polite"
              >
                Found {filteredGPOs.length} post office{filteredGPOs.length !== 1 ? "s" : ""}
              </motion.p>
            )}
          </motion.div>

          {filteredGPOs.length > 0 ? (
            <div>
              <div className="flex items-center gap-3 mb-6">
                <Building2 className="w-6 h-6 text-[#D32F2F]" aria-hidden="true" />
                <h3 className="text-[#333333] text-lg md:text-xl">
                  Post Offices ({filteredGPOs.length})
                </h3>
              </div>
              <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                {displayedGPOs.map((location, index) => (
                  <motion.div
                    key={index}
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, delay: index * 0.05 }}
                    viewport={{ once: true }}
                  >
                    <Card className="border-2 border-gray-200 hover:border-[#D32F2F] hover:shadow-lg transition-all h-full">
                      <CardHeader className="pb-3">
                        <CardTitle className="text-[#D32F2F] flex items-center gap-2 text-lg md:text-xl">
                          <Building2 className="w-5 h-5 flex-shrink-0" aria-hidden="true" />
                          <span>{location.city}</span>
                        </CardTitle>
                        <CardDescription className="text-[#666666]">{location.state}</CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <div className="space-y-2 text-sm">
                          <p className="text-[#666666]">
                            <span className="text-[#999999]">Address:</span> {location.address}
                          </p>
                          <p className="text-[#666666]">
                            <span className="text-[#999999]">Pincode:</span> <span className="font-mono">{location.pincode}</span>
                          </p>
                          {location.phone && (
                            <div className="flex items-center gap-2 text-[#666666]">
                              <Phone className="w-4 h-4 text-[#999999] flex-shrink-0" aria-hidden="true" />
                              <a 
                                href={`tel:${location.phone}`} 
                                className="hover:text-[#D32F2F] transition-colors font-mono"
                                aria-label={`Call ${location.city} post office at ${location.phone}`}
                              >
                                {location.phone}
                              </a>
                            </div>
                          )}
                        </div>
                        <motion.div
                          whileHover={{ scale: 1.02 }}
                          whileTap={{ scale: 0.98 }}
                        >
                          <Button
                            variant="outline"
                            size="sm"
                            className="w-full border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white transition-colors"
                            asChild
                          >
                            <a 
                              href={location.googleMapsLink} 
                              target="_blank" 
                              rel="noopener noreferrer"
                              aria-label={`Get directions to ${location.city} post office`}
                            >
                              <Navigation className="w-4 h-4 mr-2" aria-hidden="true" />
                              Get Directions
                            </a>
                          </Button>
                        </motion.div>
                      </CardContent>
                    </Card>
                  </motion.div>
                ))}
              </div>
              
              {/* Show More/Less Button */}
              {hasMore && !searchTerm && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.4, delay: 0.2 }}
                  viewport={{ once: true }}
                  className="text-center mt-8"
                >
                  <motion.div
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                  >
                    <Button
                      variant="outline"
                      size="lg"
                      onClick={() => setShowAll(!showAll)}
                      className="border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white px-8"
                      aria-label={showAll ? "Show less post offices" : `Show all ${filteredGPOs.length} post offices`}
                    >
                      {showAll ? (
                        <>
                          <ChevronUp className="w-5 h-5 mr-2" aria-hidden="true" />
                          Show Less
                        </>
                      ) : (
                        <>
                          <ChevronDown className="w-5 h-5 mr-2" aria-hidden="true" />
                          Show All ({filteredGPOs.length} Post Offices)
                        </>
                      )}
                    </Button>
                  </motion.div>
                </motion.div>
              )}
            </div>
          ) : (
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.4 }}
              className="text-center py-12 md:py-16"
              role="status"
              aria-live="polite"
            >
              <motion.div
                animate={{ y: [0, -10, 0] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <MapPin className="w-16 h-16 md:w-20 md:h-20 text-gray-300 mx-auto mb-4" aria-hidden="true" />
              </motion.div>
              <p className="text-[#333333] text-lg md:text-xl mb-2">No results found</p>
              <p className="text-[#666666] mb-4 text-sm md:text-base max-w-md mx-auto">
                {searchTerm 
                  ? `No post offices found matching "${searchTerm}".`
                  : "Start typing to search for post offices"
                }
              </p>
              {searchTerm && (
                <div className="bg-[#FFF8F3] rounded-xl p-4 max-w-lg mx-auto mb-6 border border-[#D32F2F]/20">
                  <p className="text-[#666666] text-sm mb-2">
                    <strong className="text-[#D32F2F]">Search tips:</strong>
                  </p>
                  <ul className="text-left text-sm text-[#666666] space-y-1">
                    <li>• Try searching by major city name (e.g., "Mumbai", "Delhi")</li>
                    <li>• Use state name (e.g., "Maharashtra", "Karnataka")</li>
                    <li>• Enter 6-digit pincode</li>
                    <li>• If your city isn't listed, search for the nearest major city</li>
                  </ul>
                </div>
              )}
              {searchTerm && (
                <motion.div
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    variant="outline"
                    onClick={() => setSearchTerm("")}
                    className="border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white"
                    aria-label="Clear search and show all post offices"
                  >
                    Clear Search
                  </Button>
                </motion.div>
              )}
            </motion.div>
          )}

          {/* Help Section */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="mt-12 space-y-6"
          >
            <div className="bg-[#FFF8F3] rounded-2xl p-6 md:p-8 border-2 border-[#D32F2F]/20 hover:border-[#D32F2F]/40 transition-colors">
              <h3 className="text-[#D32F2F] text-lg md:text-xl mb-4 text-center">Don't See Your City?</h3>
              <p className="text-[#666666] text-center mb-6 text-sm md:text-base">
                You can search for "General Post Office" or "GPO" in your city on Google Maps, or visit the India Post website for more locations.
              </p>
              <div className="flex flex-col sm:flex-row flex-wrap justify-center gap-3 md:gap-4">
                <motion.div
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    variant="outline"
                    className="border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white w-full sm:w-auto"
                    asChild
                  >
                    <a 
                      href="https://www.indiapost.gov.in" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      aria-label="Visit India Post website in new tab"
                    >
                      Visit India Post Website
                    </a>
                  </Button>
                </motion.div>
                <motion.div
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    className="bg-[#D32F2F] hover:bg-[#B71C1C] text-white w-full sm:w-auto"
                    asChild
                  >
                    <a 
                      href="https://maps.google.com/?q=General+Post+Office+near+me" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      aria-label="Find post offices near you on Google Maps"
                    >
                      <MapPin className="w-4 h-4 mr-2" aria-hidden="true" />
                      Find on Google Maps
                    </a>
                  </Button>
                </motion.div>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}