const integerFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
    minimumFractionDigits: 0,
    useGrouping: true,
});

export function parseMoney(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? Math.trunc(value) : 0;
    }

    const normalized = String(value ?? '').split(',')[0].replace(/[^\d-]/g, '');
    const parsed = Number.parseInt(normalized, 10);

    return Number.isNaN(parsed) ? 0 : parsed;
}

export function useMoney() {
    const formatAmount = (value) => integerFormatter.format(parseMoney(value));
    const formatMoney = (value, { describeNegative = true } = {}) => {
        const amount = parseMoney(value);
        const formatted = `${formatAmount(amount)} F CFA`;

        if (amount < 0 && describeNegative) {
            return `${formatted} — sortie`;
        }

        return formatted;
    };

    return {
        formatAmount,
        formatMoney,
        parseMoney,
    };
}
