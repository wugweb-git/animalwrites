import { useState, useEffect } from "react";
import { Users, Download, RefreshCw, Mail, MapPin, Calendar } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { projectId, publicAnonKey } from "../utils/supabase/info";

interface Volunteer {
  name: string;
  email: string;
  city: string;
  role: string;
  submittedAt: string;
}

export function VolunteerAdmin() {
  const [volunteers, setVolunteers] = useState<Volunteer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchVolunteers = async () => {
    setLoading(true);
    setError("");
    
    try {
      const response = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/volunteers`,
        {
          headers: {
            "Authorization": `Bearer ${publicAnonKey}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error("Failed to fetch volunteers");
      }

      const result = await response.json();
      setVolunteers(result.volunteers || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load volunteers");
      console.error("Error fetching volunteers:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVolunteers();
  }, []);

  const exportToCSV = () => {
    if (volunteers.length === 0) return;

    const headers = ["Name", "Email", "City", "Role", "Submitted At"];
    const rows = volunteers.map(v => [
      v.name,
      v.email,
      v.city,
      v.role,
      new Date(v.submittedAt).toLocaleString(),
    ]);

    const csv = [
      headers.join(","),
      ...rows.map(row => row.map(cell => `"${cell}"`).join(",")),
    ].join("\n");

    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `animal-writes-volunteers-${new Date().toISOString().split("T")[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  };

  const roleLabels: Record<string, string> = {
    coordinator: "City Coordinator",
    social: "Social Media Volunteer",
    writer: "Writing Helper",
    logistics: "Logistics Support",
    translator: "Translator",
    general: "General Volunteer",
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-[#FFF8F3] to-white py-16 px-4">
      <div className="container mx-auto max-w-7xl">
        <div className="mb-8">
          <div className="flex items-center justify-between flex-wrap gap-4">
            <div className="flex items-center gap-3">
              <Users className="w-8 h-8 text-[#F59E0B]" />
              <div>
                <h2 className="text-2xl md:text-4xl text-[#333333]">Volunteer Signups</h2>
                <p className="text-[#666666] text-sm md:text-base">
                  {volunteers.length} volunteer{volunteers.length !== 1 ? "s" : ""} registered
                </p>
              </div>
            </div>
            
            <div className="flex gap-2">
              <Button
                onClick={fetchVolunteers}
                disabled={loading}
                className="bg-[#F59E0B] hover:bg-[#D97706] text-white"
              >
                <RefreshCw className={`w-4 h-4 mr-2 ${loading ? "animate-spin" : ""}`} />
                Refresh
              </Button>
              <Button
                onClick={exportToCSV}
                disabled={volunteers.length === 0}
                className="bg-[#333333] hover:bg-[#1a1a1a] text-white"
              >
                <Download className="w-4 h-4 mr-2" />
                Export CSV
              </Button>
            </div>
          </div>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <p className="font-semibold">Error:</p>
            <p>{error}</p>
          </div>
        )}

        {loading && volunteers.length === 0 ? (
          <div className="text-center py-12">
            <RefreshCw className="w-8 h-8 text-[#F59E0B] animate-spin mx-auto mb-4" />
            <p className="text-[#666666]">Loading volunteers...</p>
          </div>
        ) : volunteers.length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center">
              <Users className="w-12 h-12 text-gray-300 mx-auto mb-4" />
              <p className="text-[#666666] text-lg">No volunteers registered yet</p>
              <p className="text-[#999999] text-sm mt-2">
                Volunteer signups will appear here once someone submits the form
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 md:gap-6">
            {volunteers.map((volunteer, index) => (
              <Card key={index} className="border-2 border-[#F59E0B]/20 hover:border-[#F59E0B] transition-colors">
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div>
                      <CardTitle className="text-[#333333] text-lg md:text-xl">
                        {volunteer.name}
                      </CardTitle>
                      <CardDescription className="mt-1">
                        {roleLabels[volunteer.role] || volunteer.role}
                      </CardDescription>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-[#999999]">
                      <Calendar className="w-4 h-4" />
                      {new Date(volunteer.submittedAt).toLocaleDateString("en-IN", {
                        year: "numeric",
                        month: "short",
                        day: "numeric",
                        hour: "2-digit",
                        minute: "2-digit",
                      })}
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="flex items-center gap-2 text-[#666666]">
                      <Mail className="w-4 h-4 text-[#F59E0B]" />
                      <a 
                        href={`mailto:${volunteer.email}`}
                        className="hover:text-[#F59E0B] transition-colors"
                      >
                        {volunteer.email}
                      </a>
                    </div>
                    <div className="flex items-center gap-2 text-[#666666]">
                      <MapPin className="w-4 h-4 text-[#F59E0B]" />
                      <span>{volunteer.city}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        <div className="mt-8 bg-[#FFF8F3] rounded-lg p-6 border border-[#F59E0B]/20">
          <h3 className="text-[#333333] font-semibold mb-2">How to Access This Page</h3>
          <p className="text-[#666666] text-sm">
            Bookmark this URL to access the volunteer admin dashboard anytime: 
            <code className="ml-2 bg-white px-2 py-1 rounded text-[#F59E0B]">
              {window.location.origin}?admin=volunteers
            </code>
          </p>
          <p className="text-[#999999] text-xs mt-2">
            Note: In production, you should protect this page with authentication.
          </p>
        </div>
      </div>
    </div>
  );
}
