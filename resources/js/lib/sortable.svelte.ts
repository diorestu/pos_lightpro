export function createSortable<T>(
    initialDataGetter: () => T[],
    defaultKey?: string,
    defaultDir: 'asc' | 'desc' = 'asc'
) {
    let sortKey = $state<string | null>(defaultKey || null);
    let sortDirection = $state<'asc' | 'desc' | null>(defaultKey ? defaultDir : null);

    function toggleSort(key: string) {
        if (sortKey === key) {
            if (sortDirection === 'asc') {
                sortDirection = 'desc';
            } else if (sortDirection === 'desc') {
                sortDirection = null;
                sortKey = null;
            } else {
                sortDirection = 'asc';
            }
        } else {
            sortKey = key;
            sortDirection = 'asc';
        }
    }

    const sortedData = $derived.by(() => {
        const raw = initialDataGetter() || [];
        const list = [...raw];
        if (!sortKey || !sortDirection) return list;

        return list.sort((a: any, b: any) => {
            let valA = a?.[sortKey!];
            let valB = b?.[sortKey!];

            // Handle nested objects or undefined
            if (valA === undefined || valA === null) valA = '';
            if (valB === undefined || valB === null) valB = '';

            const numA = Number(valA);
            const numB = Number(valB);

            if (!isNaN(numA) && !isNaN(numB) && typeof valA !== 'boolean' && typeof valB !== 'boolean' && valA !== '' && valB !== '') {
                return sortDirection === 'asc' ? numA - numB : numB - numA;
            }

            const strA = String(valA).toLowerCase();
            const strB = String(valB).toLowerCase();

            return sortDirection === 'asc'
                ? strA.localeCompare(strB, 'id-ID', { numeric: true })
                : strB.localeCompare(strA, 'id-ID', { numeric: true });
        });
    });

    return {
        get sortKey() {
            return sortKey;
        },
        get sortDirection() {
            return sortDirection;
        },
        get sortedData() {
            return sortedData;
        },
        toggleSort,
    };
}
