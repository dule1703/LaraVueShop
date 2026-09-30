export const orderStatusLabels = {
    pending: 'Na čekanju',
    processing: 'U obradi',
    paid: 'Plaćeno',
    shipped: 'Poslato',
    delivered: 'Dostavljeno',
    completed: 'Završeno',
    cancelled: 'Otkazano',
    failed: 'Neuspelo',
};

// Boja bedža po statusu — 8 stanja ne mogu da nose jednu brand-accent boju
// (isti princip kao StatusBadge.vue iz koraka 1, gde je aktivno/neaktivno
// namerno zeleno/sivo, ne brand-accent).
export const orderStatusStyles = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-blue-100 text-blue-800',
    paid: 'bg-green-100 text-green-800',
    completed: 'bg-green-100 text-green-800',
    shipped: 'bg-blue-100 text-blue-800',
    delivered: 'bg-black/5 text-brand-text-secondary',
    cancelled: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
};

export const paymentMethodLabels = {
    paypal: 'PayPal / kartica',
    cod: 'Pouzećem',
};

/**
 * Sledeći koraci dostupni iz TRENUTNOG statusa (Admin/Orders/Show.vue akcije).
 * Namerno jednostavna putanja, ne strog FSM koji bi backend nametao:
 *   pending → processing / paid / cancelled   (nepromenjeno iz starog UI-ja)
 *   processing → paid / shipped / cancelled
 *   paid → shipped / cancelled
 *   shipped → delivered
 *   delivered / completed / cancelled / failed → terminalno, nema akcija
 *
 * Razlog: sistem sam postavlja samo 'pending' (kreiranje porudžbine),
 * 'paid' (uspešan PayPal capture) i 'cancelled'/'failed' (PayPal
 * cancel/neuspeh, sa povratom zaliha — vidi InventoryService, Faza 5).
 * 'processing'/'shipped'/'delivered'/'completed' su isključivo ručne admin
 * akcije (npr. za COD porudžbine, koje ostaju 'pending' dok admin ne
 * potvrdi tok ispunjenja) — otud je otkazivanje dostupno samo dok
 * porudžbina još nije poslata (posle slanja, otkazivanje nema smisla u
 * ovom modelu). Ne enforce-uje se na backend-u (OrderController::update i
 * dalje prihvata bilo koji od 8 statusa) — čisto UI vođenje kroz smislen
 * redosled, lako izmenjivo kasnije ako se pokaže pogrešno.
 */
export const orderStatusTransitions = {
    pending: ['processing', 'paid', 'cancelled'],
    processing: ['paid', 'shipped', 'cancelled'],
    paid: ['shipped', 'cancelled'],
    shipped: ['delivered'],
    delivered: [],
    completed: [],
    cancelled: [],
    failed: [],
};
