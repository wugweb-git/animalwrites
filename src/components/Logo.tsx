interface LogoProps {
  className?: string;
  size?: number;
}

export function Logo({ className = "", size = 64 }: LogoProps) {
  return (
    <img
      src="https://vedanshu.design/AW/logo"
      alt="Animal Writes Logo"
      width={size}
      height={size}
      className={className}
      style={{ width: size, height: size, objectFit: 'contain' }}
    />
  );
}
