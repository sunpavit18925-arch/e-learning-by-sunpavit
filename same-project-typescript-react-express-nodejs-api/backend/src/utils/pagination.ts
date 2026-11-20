export function positiveInteger(value: unknown, fallback: number, maximum: number): number {
  const parsed = typeof value === 'string' ? Number(value) : NaN;
  return Number.isFinite(parsed) ? Math.min(maximum, Math.max(1, Math.floor(parsed))) : fallback;
}

export function pagination(query: Record<string, unknown>, total: number, defaultSize = 20) {
  const pageSize = positiveInteger(query.limit, defaultSize, 50);
  const totalPages = Math.ceil(total / pageSize);
  const page = Math.min(positiveInteger(query.page, 1, 1_000_000), Math.max(1, totalPages));
  return { total, page, pageSize, totalPages, offset: (page - 1) * pageSize };
}

export function searchPattern(value: unknown): string {
  const text = typeof value === 'string' ? value.trim().slice(0, 200) : '';
  return '%' + text.replace(/[=%_]/g, '=$&') + '%';
}
