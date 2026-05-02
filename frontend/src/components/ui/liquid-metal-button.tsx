import { Sparkles } from "lucide-react";
import type React from "react";
import { useState } from "react";

interface LiquidMetalButtonProps {
  label?: string;
  onClick?: () => void;
  viewMode?: "text" | "icon";
  className?: string;
}

export function LiquidMetalButton({
  label = "Get Started",
  onClick,
  viewMode = "text",
  className = "",
}: LiquidMetalButtonProps) {
  const [isHovered, setIsHovered] = useState(false);

  const dimensions = viewMode === "icon" 
    ? { width: 46, height: 46 } 
    : { width: 142, height: 46 };

  return (
    <button
      onClick={onClick}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      className={`relative inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-300 ${
        isHovered ? "bg-zinc-800 scale-[1.02]" : "bg-zinc-900"
      } border border-white/20 text-white shadow-lg active:scale-[0.98] ${className}`}
      style={{
        width: `${dimensions.width}px`,
        height: `${dimensions.height}px`,
      }}
    >
      {viewMode === "icon" ? (
        <Sparkles size={18} className="text-white" />
      ) : (
        <>
          <span className="text-sm">{label}</span>
          <Sparkles size={14} className={`transition-transform duration-500 ${isHovered ? "rotate-12 scale-110" : ""}`} />
        </>
      )}
    </button>
  );
}
