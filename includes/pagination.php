<?php
function elearning_pagination(int $total, int $size = 20): array
{
    $pages = (int)ceil($total / $size);
    $page = min(max(1, (int)($_GET['page'] ?? 1)), max(1, $pages));
    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'size' => $size, 'offset' => ($page - 1) * $size];
}

function elearning_render_pagination(array $paging): void
{
    if ($paging['pages'] <= 1) {
        return;
    }
    echo '<nav aria-label="แบ่งหน้า" class="flex flex-wrap items-center justify-center gap-3 mt-8 text-sm">';
    if ($paging['page'] > 1) {
        echo '<a class="px-4 py-2 border rounded-xl" href="?page=' . ($paging['page'] - 1) . '">ก่อนหน้า</a>';
    }
    echo '<span>หน้า ' . $paging['page'] . ' / ' . $paging['pages'] . '</span>';
    if ($paging['page'] < $paging['pages']) {
        echo '<a class="px-4 py-2 border rounded-xl" href="?page=' . ($paging['page'] + 1) . '">ถัดไป</a>';
    }
    echo '</nav>';
}
