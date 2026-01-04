<?php
/**
 * AI Sites Manager - Telebridge Integration Addon
 * Makes checkboxes pre-selected based on auto-categorization
 * 
 * Add this code to: readyprompt-site (2).php
 * Inside the render_meta_box function, after getting $saved_checks
 * 
 * @version 1.0
 */

// ============================================
// 1. AUTO-SELECT CHECKBOXES FROM CATEGORIZATION
// ============================================

// In render_meta_box(), modify the checkbox loop:
// Replace the original checkbox rendering with this:

?>

<!-- UPDATED: Meta Box Rendering with Auto-Selected Checkboxes -->

<div class="ai-wrap">
    <!-- Header & Search -->
    <div class="ai-header-bar">
        <input type="text" id="ai_search" class="ai-search" placeholder="🔍 جستجو (مثلاً: Midjourney)...">
        <label class="ai-switch-label">
            <input type="checkbox" name="ai_sites_auto_display" value="yes" <?php checked($auto_display, 'yes'); ?>>
            نمایش خودکار در صفحه محصول
        </label>
    </div>

    <!-- Grouped Items with Auto-Select Support -->
    <div id="ai_container">
        <?php foreach ($categories as $cat_slug => $cat_info) : 
            if (!isset($grouped_list[$cat_slug])) continue;
        ?>
            <div class="ai-group">
                <div class="ai-group-title">
                    <span class="ai-icon"><?php echo $cat_info['icon']; ?></span>
                    <?php echo esc_html($cat_info['label']); ?>
                </div>
                <div class="ai-group-content">
                    <?php foreach ($grouped_list[$cat_slug] as $key => $data) : 
                        // ✅ Check if this site was auto-selected
                        $is_checked = in_array($key, $saved_checks);
                        $auto_selected_class = $is_checked ? 'ai-item-auto-selected' : '';
                    ?>
                        <div class="ai-item <?php echo $auto_selected_class; ?>" 
                             data-name="<?php echo esc_attr(strtolower($data['name'])); ?>"
                             data-key="<?php echo esc_attr($key); ?>">
                            <input type="checkbox" 
                                   id="ai_<?php echo esc_attr($key); ?>" 
                                   name="ai_sites_checks[]" 
                                   value="<?php echo esc_attr($key); ?>" 
                                   <?php checked($is_checked); ?>>
                            <label for="ai_<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($data['name']); ?>
                            </label>
                            <?php if ($is_checked) : ?>
                                <span class="ai-auto-badge" title="انتخاب خودکار">✓</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Custom Sites -->
    <div class="ai-add-box">
        <h4 style="margin: 0 0 10px; font-size: 13px; color: #374151;">➕ افزودن لینک سفارشی / سرویس</h4>
        <div id="ai_repeater">
            <?php foreach ($saved_custom as $item) : 
                $c_cat = $item['cat'] ?? 'tool';
            ?>
                <div class="ai-row">
                    <input type="text" class="ai-input" name="ai_custom_names[]" value="<?php echo esc_attr($item['name']); ?>" style="flex:1" placeholder="نام">
                    <input type="url" class="ai-input" name="ai_custom_urls[]" value="<?php echo esc_url($item['url']); ?>" style="flex:1" placeholder="لینک">
                    <select class="ai-select" name="ai_custom_cats[]">
                        <?php foreach($categories as $k => $v) echo '<option value="'.$k.'" '.selected($c_cat, $k, false).'>'.$v['label'].'</option>'; ?>
                    </select>
                    <span class="dashicons dashicons-trash ai-trash"></span>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button ai-btn-add" id="ai_add_btn">افزودن مورد جدید</button>
    </div>
</div>

<style>
    /* ✅ Auto-Selected Visual Indicator */
    .ai-item-auto-selected {
        background: #e6f4ea !important;
        border-color: #188038 !important;
    }

    .ai-auto-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #188038;
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        font-size: 12px;
        font-weight: bold;
        margin-right: 6px;
    }

    .ai-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // ✅ Search functionality (existing)
    $('#ai_search').on('keyup', function() {
        var val = $(this).val().toLowerCase();
        $('.ai-item').each(function() {
            $(this).toggle($(this).data('name').indexOf(val) > -1);
        });
        $('.ai-group').each(function() {
            var visible = $(this).find('.ai-item:visible').length > 0;
            $(this).toggle(visible);
        });
    });

    // ✅ Add Custom Site Row (existing)
    var idx = 0;
    $('#ai_add_btn').click(function() {
        var options = '';
        <?php foreach($categories as $k => $v) : ?>
            options += '<option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>';
        <?php endforeach; ?>

        var row = `
        <div class="ai-row">
            <input type="text" class="ai-input" name="ai_new_names[${idx}]" style="flex:1" placeholder="نام سایت" required>
            <input type="url" class="ai-input" name="ai_new_urls[${idx}]" style="flex:1" placeholder="لینک (https://...)" required>
            <select class="ai-select" name="ai_new_cats[${idx}]">${options}</select>
            <label class="ai-global-toggle" title="ذخیره برای تمام محصولات">
                <input type="checkbox" name="ai_make_global[${idx}]" value="yes"> سرویس‌پهلو
            </label>
            <span class="dashicons dashicons-trash ai-trash"></span>
        </div>`;
        $('#ai_repeater').append(row);
        idx++;
    });

    $(document).on('click', '.ai-trash', function() { 
        $(this).closest('.ai-row').remove(); 
    });

    // ✅ NEW: Visual feedback when auto-selected items exist
    var autoSelectedCount = $('.ai-item-auto-selected').length;
    if (autoSelectedCount > 0) {
        console.log('✅ Telebridge: ' + autoSelectedCount + ' site(s) pre-selected automatically');
    }
});
</script>

<?php
/**
 * ============================================
 * 2. UPDATE: Add to save_meta_data function
 * ============================================
 * 
 * This ensures auto-selected items are properly saved
 * (No changes needed - existing code handles it)
 */

/**
 * ============================================
 * 3. INTEGRATION POINT IN RTI_AUTO_CATEGORIZATION
 * ============================================
 * 
 * The auto-categorization module already saves
 * checked items correctly via:
 * 
 * update_post_meta($product_id, '_ai_sites_checked', $selected_keys);
 * 
 * So the checkboxes will be automatically checked
 * when render_meta_box() is called again.
 */
?>