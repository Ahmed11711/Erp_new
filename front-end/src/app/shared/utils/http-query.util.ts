/** تجنّب إلحاق ? بعنوان طلب GET عندما لا توجد معاملات فعلية */
export function httpOptionsFromParams(
  params?: Record<string, string | number | boolean | null | undefined>
): { params?: Record<string, string | number | boolean> } {
  if (!params) {
    return {};
  }
  const cleaned: Record<string, string | number | boolean> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '') {
      continue;
    }
    cleaned[key] = value;
  }
  return Object.keys(cleaned).length ? { params: cleaned } : {};
}
