"use client"

import { motion } from "motion/react"
import { Check } from "lucide-react"
import {
  PricingTier,
  TierActionMeta,
  formatTierPrice,
  getDurationLabel,
  getTierAccent,
  getTierButtonTextColor,
  getTierFeatures,
  getTierRank,
  rgba,
} from "./pricing-blocks"

interface PricingCreativeProps {
  tiers: PricingTier[]
  currentTier?: string
  loadingTier?: string | null
  onSelectTier?: (tier: PricingTier) => void
  showCta?: boolean
  getActionMeta?: (tier: PricingTier) => TierActionMeta
}

function defaultActionMeta(
  tier: PricingTier,
  currentTier: string,
  loadingTier: string | null,
): TierActionMeta {
  const tierName = (tier.tier_name || "basic").toLowerCase()

  if (currentTier === tierName) {
    return { label: "Current Plan", disabled: true }
  }

  if (loadingTier === tierName) {
    return { label: "Processing...", disabled: true }
  }

  if (Number(tier.price || 0) <= 0) {
    return { label: "Start Free", secondary: true }
  }

  return { label: "Get Started" }
}

function buildOrderedTiers(tiers: PricingTier[]) {
  const ranked = [...tiers].sort((left, right) => getTierRank(left.tier_name) - getTierRank(right.tier_name))
  const featuredIndex = ranked.findIndex((tier) => (tier.tier_name || "").toLowerCase() === "premium")

  if (ranked.length === 3 && featuredIndex === 2) {
    return [ranked[0], ranked[2], ranked[1]]
  }

  return ranked
}

export default function PricingCreative({
  tiers,
  currentTier = "basic",
  loadingTier = null,
  onSelectTier,
  showCta = true,
  getActionMeta,
}: PricingCreativeProps) {
  const orderedTiers = buildOrderedTiers(tiers)

  return (
    <section className="relative flex flex-col items-center py-24">
      <style>{`
        .pricing-plans-track::-webkit-scrollbar {
          display: none;
        }
      `}</style>

      <div className="pricing-plans-track flex w-full flex-col items-center justify-center gap-8 md:flex-row md:items-stretch md:justify-center md:overflow-x-auto md:px-6 md:pb-4 md:[scrollbar-width:none]">
        {orderedTiers.map((tier, index) => {
          const tierName = (tier.tier_name || "basic").toLowerCase()
          const accent = getTierAccent(tier)
          const isFeatured = tierName === "premium"
          const isActive = currentTier === tierName
          const buttonText = getTierButtonTextColor(accent)
          const actionMeta = getActionMeta?.(tier) ?? defaultActionMeta(tier, currentTier, loadingTier)
          const features = getTierFeatures(tier).slice(0, isFeatured ? 6 : 4)

          const cardClassName = isFeatured
            ? "relative z-20 w-full max-w-[22rem] rounded-3xl border-4 px-8 py-10 text-[#1a1a1a] shadow-xl transition-transform hover:scale-[1.02] md:w-80 md:scale-110 md:px-10 md:py-14 md:hover:scale-[1.12]"
            : "relative z-10 w-full max-w-[20rem] rounded-2xl border px-7 py-8 text-foreground shadow-sm transition-transform hover:scale-105 md:w-72 md:px-8 md:py-10 bg-card"

          return (
            <motion.div
              key={tier.id}
              initial={{
                opacity: 0,
                y: isFeatured ? 60 : 40,
                rotate: isFeatured ? 0 : index === 0 ? -6 : 6,
              }}
              whileInView={{
                opacity: 1,
                y: isFeatured ? -20 : 0,
                rotate: isFeatured ? 0 : index === 0 ? -6 : 6,
              }}
              viewport={{ once: true, amount: 0.25 }}
              transition={{ type: "spring", duration: isFeatured ? 0.7 : index === 0 ? 0.5 : 0.6 }}
              className={cardClassName}
              style={
                isFeatured
                  ? {
                      borderColor: rgba(accent, 0.5),
                      background: `linear-gradient(to bottom, ${rgba(accent, 0.88)}, ${rgba(accent, 0.74)})`,
                    }
                  : {
                      borderColor: rgba(accent, 0.3),
                      background: "rgba(0,0,0,0.4)",
                    }
              }
            >
              {isFeatured ? (
                <motion.div
                  animate={{ y: [10, 6, 10] }}
                  transition={{ repeat: Number.POSITIVE_INFINITY, duration: 2, ease: "easeInOut" }}
                  className="absolute -top-6 left-1/2 -translate-x-1/2 rounded-full px-5 py-1 text-xs font-extrabold shadow"
                  style={{
                    background: accent,
                    color: buttonText,
                    border: `1px solid ${rgba(accent, 0.22)}`,
                  }}
                >
                  Best Deal
                </motion.div>
              ) : null}

              <div
                className="mb-2 text-lg font-bold"
                style={{ color: isFeatured ? buttonText : accent }}
              >
                {tierName === "basic" ? "Starter" : `${tier.tier_name.charAt(0).toUpperCase()}${tier.tier_name.slice(1)} Seller`}
              </div>

              <div
                className={`mb-4 font-extrabold ${isFeatured ? "text-5xl font-black" : "text-3xl"}`}
                style={{ color: isFeatured ? buttonText : "#ffffff" }}
              >
                {formatTierPrice(tier.price)}
              </div>

              <div
                className={`mb-4 ${isFeatured ? "text-sm" : "text-sm"}`}
                style={{ color: isFeatured ? rgba(buttonText, 0.78) : "rgba(255,255,255,0.7)" }}
              >
                {Number(tier.price || 0) > 0 ? `/${getDurationLabel(tier.duration)}` : "No payment required"}
              </div>

              <ul
                className={`mb-6 space-y-2 ${isFeatured ? "text-base" : "text-sm"}`}
                style={{ color: isFeatured ? rgba(buttonText, 0.88) : "rgba(255,255,255,0.7)" }}
              >
                {features.map((feature) => (
                  <li key={`${tier.id}-${feature}`} className="flex items-start gap-2">
                    <span
                      className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                      style={{
                        background: isFeatured ? rgba(buttonText, 0.16) : rgba(accent, 0.18),
                        color: isFeatured ? buttonText : "#34d399",
                      }}
                    >
                      <Check className="h-3.5 w-3.5" />
                    </span>
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>

              {showCta ? (
                <button
                  type="button"
                  disabled={actionMeta.disabled || !onSelectTier}
                  onClick={() => onSelectTier?.(tier)}
                  className="w-full rounded-md py-2 font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                  style={{
                    background: actionMeta.secondary
                      ? "transparent"
                      : isFeatured
                        ? "#111111"
                        : accent,
                    color: actionMeta.secondary
                      ? isFeatured
                        ? buttonText
                        : accent
                      : isFeatured
                        ? "#ffffff"
                        : getTierButtonTextColor(accent),
                    border: `1px solid ${
                      actionMeta.secondary
                        ? isFeatured
                          ? rgba(buttonText, 0.3)
                          : rgba(accent, 0.3)
                        : "transparent"
                    }`,
                  }}
                >
                  {actionMeta.label}
                </button>
              ) : (
                <div
                  className="w-full rounded-md py-2 text-center text-xs font-semibold uppercase tracking-[0.14em]"
                  style={{
                    background: isFeatured ? rgba(buttonText, 0.12) : rgba(accent, 0.12),
                    color: isFeatured ? buttonText : accent,
                    border: `1px solid ${isFeatured ? rgba(buttonText, 0.16) : rgba(accent, 0.22)}`,
                  }}
                >
                  {isActive ? "Active Plan" : "Tier Overview"}
                </div>
              )}
            </motion.div>
          )
        })}
      </div>
    </section>
  )
}
