import type { OrderState } from '@/types/domain';

export const ORDER_STATE_TRANSITIONS: Readonly<Record<OrderState, ReadonlyArray<OrderState>>> = {
  OFFER_RECEIVED:  ['OFFER_EXPIRED', 'OFFER_REJECTED', 'ACCEPTED'],
  OFFER_EXPIRED:   ['CANCELLED'],
  OFFER_REJECTED:  ['CANCELLED'],
  ACCEPTED:        ['NAVIGATING_TO_PICKUP', 'CANCELLED'],
  NAVIGATING_TO_PICKUP: ['ARRIVED_AT_PICKUP', 'CANCELLED'],
  ARRIVED_AT_PICKUP:    ['ORDER_VERIFIED', 'CANCELLED'],
  ORDER_VERIFIED:       ['NAVIGATING_TO_DROP_OFF', 'CANCELLED'],
  NAVIGATING_TO_DROP_OFF: ['ARRIVED_AT_DROP_OFF', 'CANCELLED'],
  ARRIVED_AT_DROP_OFF:   ['PROOF_SUBMITTED', 'CANCELLED'],
  PROOF_SUBMITTED:       ['COMPLETED'],
  COMPLETED:             [],
  CANCELLED:             [],
};

export function canTransition(from: OrderState, to: OrderState): boolean {
  return ORDER_STATE_TRANSITIONS[from]?.includes(to) ?? false;
}

export interface StepMeta {
  step: number | null;
  total: 2 | 0;
  label: string;
  short: string;
  objective: string | null;
  next: OrderState | null;
  actionLabel: string | null;
}

export function describeDeliveryProgress(state: OrderState): StepMeta {
  switch (state) {
    case 'ACCEPTED':
    case 'NAVIGATING_TO_PICKUP':
    case 'ARRIVED_AT_PICKUP':
    case 'ORDER_VERIFIED':
      return {
        step: 1,
        total: 2,
        label: 'Proceed to Merchant',
        short: 'Pickup',
        objective: 'Proceed to the merchant and verify the order',
        next: 'ORDER_VERIFIED',
        actionLabel: state === 'ARRIVED_AT_PICKUP' ? 'SWIPE TO CONFIRM ITEMS VERIFIED' : 'SWIPE TO CONFIRM ARRIVAL AT STORE',
      };
    case 'NAVIGATING_TO_DROP_OFF':
    case 'ARRIVED_AT_DROP_OFF':
    case 'PROOF_SUBMITTED':
      return {
        step: 2,
        total: 2,
        label: 'Deliver to Customer',
        short: 'Drop-off',
        objective: 'Deliver to the customer and submit proof of delivery',
        next: 'PROOF_SUBMITTED',
        actionLabel: state === 'ARRIVED_AT_DROP_OFF' ? 'SWIPE TO CONFIRM DROP-OFF' : 'SWIPE TO CONFIRM ARRIVAL AT CUSTOMER',
      };
    case 'COMPLETED':
      return { step: null, total: 0, label: 'Completed', short: 'Done', objective: null, next: null, actionLabel: null };
    case 'CANCELLED':
      return { step: null, total: 0, label: 'Cancelled', short: 'Cancelled', objective: null, next: null, actionLabel: null };
    default:
      return { step: null, total: 0, label: 'Awaiting', short: 'Pending', objective: null, next: null, actionLabel: null };
  }
}

export function haversineMeters(
  latA: number,
  lngA: number,
  latB: number,
  lngB: number,
): number {
  const R = 6_371_000;
  const φ1 = (latA * Math.PI) / 180;
  const φ2 = (latB * Math.PI) / 180;
  const Δφ = ((latB - latA) * Math.PI) / 180;
  const Δλ = ((lngB - lngA) * Math.PI) / 180;
  const a =
    Math.sin(Δφ / 2) ** 2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
}
