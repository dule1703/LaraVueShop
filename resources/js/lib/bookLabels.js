export const formatLabels = {
    hardcover: 'Tvrd povez',
    paperback: 'Mek povez',
    ebook: 'E-knjiga',
};

export const scriptLabels = {
    Cyrl: 'Ćirilica',
    Latn: 'Latinica',
};

export const roleLabels = {
    author: 'Autor',
    translator: 'Prevodilac',
    illustrator: 'Ilustrator',
    editor: 'Urednik',
};

export function languageLabel(code) {
    try {
        const name = new Intl.DisplayNames(['sr-Latn'], { type: 'language' }).of(code);
        return name ? name.charAt(0).toUpperCase() + name.slice(1) : code;
    } catch {
        return code;
    }
}

// DB drajver određuje da li cena stiže kao broj (SQLite) ili string (MariaDB).
export function formatPrice(price) {
    return `${Number(price).toFixed(2)} €`;
}

// stock === null znači neograničene zalihe (e-knjiga).
export function availabilityLabel(book) {
    if (book.stock === null) return 'Dostupno';
    return book.available ? 'Na stanju' : 'Nema na stanju';
}
