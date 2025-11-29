interface LogoProps {
  className?: string;
  size?: number;
}

export function Logo({ className = "", size = 64 }: LogoProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 100 100"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
    >
      {/* Background Circle */}
      <circle cx="50" cy="50" r="48" fill="#F59E0B" />
      
      {/* Inner Circle */}
      <circle cx="50" cy="50" r="42" fill="#333333" />
      
      {/* Paw Print Design */}
      {/* Main Pad */}
      <ellipse cx="50" cy="55" rx="12" ry="15" fill="#F59E0B" />
      
      {/* Top Left Toe */}
      <ellipse cx="38" cy="42" rx="6" ry="8" fill="#F59E0B" transform="rotate(-15 38 42)" />
      
      {/* Top Center Left Toe */}
      <ellipse cx="45" cy="38" rx="6" ry="8" fill="#F59E0B" transform="rotate(-5 45 38)" />
      
      {/* Top Center Right Toe */}
      <ellipse cx="55" cy="38" rx="6" ry="8" fill="#F59E0B" transform="rotate(5 55 38)" />
      
      {/* Top Right Toe */}
      <ellipse cx="62" cy="42" rx="6" ry="8" fill="#F59E0B" transform="rotate(15 62 42)" />
      
      {/* Envelope element at bottom */}
      <path
        d="M 30 70 L 50 78 L 70 70 L 70 75 L 50 83 L 30 75 Z"
        fill="#F59E0B"
        opacity="0.8"
      />
    </svg>
  );
}
