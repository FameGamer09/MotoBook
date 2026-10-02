export type OrderState =
  | 'OFFER_RECEIVED'
  | 'OFFER_EXPIRED'
  | 'OFFER_REJECTED'
  | 'ACCEPTED'
  | 'NAVIGATING_TO_PICKUP'
  | 'ARRIVED_AT_PICKUP'
  | 'ORDER_VERIFIED'
  | 'NAVIGATING_TO_DROP_OFF'
  | 'ARRIVED_AT_DROP_OFF'
  | 'PROOF_SUBMITTED'
  | 'COMPLETED'
  | 'CANCELLED';

export type PaymentMethod =
  | 'COD'
  | 'GCASH'
  | 'MAYA'
  | 'GRABPAY'
  | 'SHOPEEPAY'
  | 'CARD'
  | 'BANK_TRANSFER'
  | 'PREPAID';

export type PaymentStatus =
  | 'UNPAID'
  | 'PENDING_VERIFICATION'
  | 'PAID';

export type SelectionType = 'radio' | 'checkbox';
export type HandoffMode = 'DIRECT' | 'GATE_LEAVE' | 'NEIGHBOR' | 'LOCKER';
export type MotionState = 'MOVING' | 'IDLE' | 'STOPPED';
export type RiderStatus = 'ACTIVE' | 'INACTIVE' | 'ON_SHIFT' | 'OFFLINE' | 'SUSPENDED';
export type VehicleType = 'MOTORCYCLE' | 'EBIKE' | 'CAR' | 'VAN';
export type IncidentCode =
  | 'STORE_CLOSED'
  | 'LONG_STORE_WAIT_15'
  | 'ORDER_MISSING'
  | 'ITEM_UNAVAILABLE'
  | 'MERCHANT_CONGESTION'
  | 'ITEM_DAMAGED'
  | 'CUSTOMER_UNREACHABLE'
  | 'WRONG_ADDRESS'
  | 'MECHANICAL_ISSUE'
  | 'OTHER';

export interface RiderProfile {
  id: number;
  rider_code: string;
  name: string;
  email: string;
  phone: string | null;
  vehicle_plate: string | null;
  vehicle_type: VehicleType | null;
  city: string | null;
  status: RiderStatus;
  duty_today_payout: number;
  completed_today: number;
  acceptance_rate: number;
  active_hours_today: number;
  current_shift_started_at: string | null;
  gcash_mobile_number?: string | null;
  gcash_account_name?: string | null;
  gcash_qr_data_uri?: string | null;
  fcm_push_token?: string | null;
  fcm_push_sub_json?: string | null;
}

export interface OrderItemOption {
  group: string;
  name: string;
  price_delta?: number | null;
}

export interface OrderItem {
  id?: string | number;
  name: string;
  qty: number;
  special_instructions?: string | null;
  unit_price?: number | null;
  options?: OrderItemOption[];
}

export interface OrderEntity {
  id: number;
  rider_id: number | null;
  order_code: string;
  merchant_order_id: string | null;
  source_store_id: number | null;
  source_system: string | null;
  merchant_name: string;
  merchant_phone?: string | null;
  merchant_lat: number | null;
  merchant_lng: number | null;
  merchant_address: string;
  dropoff_name: string | null;
  dropoff_lat: number | null;
  dropoff_lng: number | null;
  dropoff_address: string;
  dropoff_phone: string | null;
  total_distance_km: number;
  estimated_minutes: number;
  payout_amount: number;
  tip_amount: number;
  cod_amount: number;
  items: OrderItem[];
  special_notes: string | null;
  state: OrderState;
  offer_received_at: string;
  offer_expires_at: string | null;
  accepted_at: string | null;
  pickup_arrived_at: string | null;
  verified_at: string | null;
  dropoff_arrived_at: string | null;
  completed_at: string | null;
  incident_reported: string | null;
  created_at: string;
  updated_at: string;
  payment_method?: PaymentMethod | null;
  payment_reference?: string | null;
  payment_status?: PaymentStatus | null;
}

export interface GeofencePoint {
  label: 'pickup' | 'dropoff';
  lat: number;
  lng: number;
  radiusMeters: 50 | 30;
}

export interface LocationPoint {
  lat: number;
  lng: number;
  heading?: number | null;
  speed_kmh?: number | null;
  accuracy_m?: number | null;
  motion?: MotionState;
  offline?: boolean;
  t_ms: number;
}

export interface RiderBootPayload {
  riderId: number;
  riderName: string;
  riderEmail: string;
  riderStatus: RiderStatus;
  riderCode: string;
  vehiclePlate: string | null;
  unifiedLogoutUrl: string;
  apiBase: string;
  buildHash: string;
}

export interface IncidentReport {
  code: IncidentCode;
  detail?: string;
}

export interface PoDPayload {
  proof_image_data?: string | null;
  proof_lat?: number | null;
  proof_lng?: number | null;
  proof_captured_at_ms?: number | null;
  cod_collected?: number | null;
  cod_change_due?: number | null;
  cod_confirmed?: boolean;
  signature_data?: string | null;
  signature_name?: string | null;
  dropoff_notes?: string | null;
  handoff_mode?: HandoffMode | null;
  photoUri?: string | null;
  photoLat?: number | null;
  photoLng?: number | null;
  signatureUri?: string | null;
  signerName?: string | null;
  cashReceived?: number | null;
  changeDue?: number | null;
  payment_method?: PaymentMethod | null;
  payment_reference?: string | null;
  payment_confirmed?: boolean;
  payment_proof_image_data?: string | null;
  payment_proof_lat?: number | null;
  payment_proof_lng?: number | null;
  payment_proof_captured_at_ms?: number | null;
  paymentProofUri?: string | null;
  paymentProofLat?: number | null;
  paymentProofLng?: number | null;
  paymentProofCapturedAtMs?: number | null;
}

export interface ChecklistItem {
  name: string;
  qty: number;
  notes?: string | null;
  checked: boolean;
  critical: boolean;
}

export type DepositStatus = 'PENDING' | 'VERIFIED' | 'REJECTED' | 'MISMATCH';

export interface RiderDepositEntity {
  id: number;
  riderId: number;
  deposit_date: string;
  orders_completed_count: number;
  cash_cod_collected_php: number;
  online_confirmed_php: number;
  total_expected_php: number;
  deposited_cash_php: number;
  deposit_note?: string | null;
  deposit_slip_image_path?: string | null;
  deposit_status: DepositStatus;
  shift_started_at?: string | null;
  shift_ended_at?: string | null;
  created_at: string;
  updated_at: string;
}

export type LedgerBreakdown = Partial<Record<PaymentMethod | string, number>>;

export interface LedgerDayEntry {
  date: string;
  completed: number;
  payout_php: number;
  tips_php: number;
  cod_collected_php: number;
  online_confirmed_php: number;
  breakdown_by_method: LedgerBreakdown;
}

export interface LedgerOrdersPerDay {
  date: string;
  completed: number;
}

export interface LedgerResponse {
  period: LedgerDayEntry[];
  orders_per_day: LedgerOrdersPerDay[];
}

export interface DepositSubmitPayload {
  cash_deposited_php: number;
  deposit_note?: string;
  deposit_slip_image_data?: string | null;
  lat?: number | null;
  lng?: number | null;
  capturedAtMs?: number | null;
  shift_started_at?: string | null;
  shift_ended_at?: string | null;
}

export interface ClientLocationPoint {
  order_id: number;
  lat: number;
  lng: number;
  accuracy_m?: number | null;
  heading?: number | null;
  speed_kmh?: number | null;
  t_ms: number;
  sharing_active: boolean;
  phone_battery_pct?: number | null;
}

export type ClientLocationState = Record<number, ClientLocationPoint | null>;

export const CLIENT_LOCATION_STALE_MS = 60_000;
export const CLIENT_LOCATION_POLL_MS = 5_000;
export const CLIENT_LOCATION_SUSPEND_HIDDEN_MS = 30_000;
