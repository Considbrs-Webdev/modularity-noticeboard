(function($, acf){

    // Format a Date object as YYYY-MM-DD
    function formatDateYMD(d){
        var yyyy = d.getFullYear();
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return yyyy + '-' + mm + '-' + dd;
    }

    // Fetch term archiving settings via AJAX and invoke cb(data) or cb(null)
    function fetchTermArchiving(termId, cb){
        if(!termId) { cb(null); return; }
        $.post(
            modularityNoticeboard.ajaxUrl,
            {
                action: 'modularity_noticeboard_get_term_archiving',
                term_id: termId,
                nonce: modularityNoticeboard.nonce
            },
            function(res){
                if(!res || !res.success){ cb(null); return; }
                cb(res.data);
            },
            'json'
        );
    }

    // Set both the hidden ACF value and the visible datepicker input
    function setArchiveDate(archiveFieldKey, archiveField, str){
        var shortKey = archiveFieldKey.replace(/^field_/, '');

        // Hidden ACF input storing the saved value
        var $hidden = $('#acf-field_' + shortKey + ', input[name="acf[' + archiveFieldKey + ']"]');
        if($hidden.length){
            $hidden.val(str).trigger('change');
        }

        // Visible datepicker input inside the same ACF field
        var $visible = $('#acf-field_' + shortKey).closest('.acf-field').find('input.hasDatepicker, input[type="text"]').first();
        if(!$visible || !$visible.length){
            $visible = $('input.hasDatepicker, input[type="text"]').filter(function(){
                return $(this).closest('.acf-field').data('key') === archiveFieldKey;
            }).first();
        }

        if($visible && $visible.length){
            $visible.val(str).trigger('change');
            if(typeof $visible.datepicker === 'function'){
                try{ $visible.datepicker('setDate', str); } catch(e){}
            }
        }

        // Fallback to ACF field API
        if(archiveField && typeof archiveField.val === 'function'){
            try{ archiveField.val(str); } catch(e){}
        }
    }

    acf.add_action('ready append', function($el){
        var noticeFieldKey = 'field_69679b0c8b9bf'; // notice_type taxonomy field key
        var archiveFieldKey = 'field_69679a808b9be'; // archive_date field key

        var noticeTypeField = acf.getField(noticeFieldKey);
        var archiveField = acf.getField(archiveFieldKey);

        if(!noticeTypeField) { return; }

        // Try multiple ways to find the underlying select/input (handles Select2)
        var $select = null;

        if(noticeTypeField.$input && noticeTypeField.$input.length) {
            $select = noticeTypeField.$input;
        }

        if((!$select || !$select.length) && noticeTypeField.$el) {
            $select = noticeTypeField.$el.find('select, input[type="hidden"]');
        }

        if((!$select || !$select.length)) {
            $select = $('#acf-field_' + noticeFieldKey + ', select[name="acf[' + noticeFieldKey +']"], select[name="notice_type"]');
        }

        if(!$select || !$select.length) { return; }

        function onTermSelected(val){
            if(!val) { return; }
            fetchTermArchiving(val, function(data){
                if(!data) { return; }

                // Prefer server-calculated archive_date
                if(data.archive_date) {
                    setArchiveDate(archiveFieldKey, archiveField, data.archive_date);
                    return;
                }

                // Fallback: compute client-side when server did not provide a date
                if(!data.automatic || !data.days) { return; }
                var days = parseInt(data.days, 10);
                if(isNaN(days) || days <= 0) { return; }
                var d = new Date();
                d.setDate(d.getDate() + days);
                var dateStr = formatDateYMD(d);
                setArchiveDate(archiveFieldKey, archiveField, dateStr);
            });
        }

        // Bind to regular change
        $select.off('.mod_nb').on('change.mod_nb', function(){
            var val = $(this).val();
            onTermSelected(val);
        });

        // Bind to Select2 selection event as well
        $select.off('select2:select.mod_nb').on('select2:select.mod_nb', function(e){
            var val = $(this).val();
            if(!val && e && e.params && e.params.data && e.params.data.id) {
                val = e.params.data.id;
            }
            onTermSelected(val);
        });

    });

})(jQuery, acf);