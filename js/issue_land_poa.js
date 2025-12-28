// issue_land_poa.js
// Requires jQuery and SweetAlert2 (both are loaded in layout.php)

$(function () {
    const villagesByCommunity = window.poaVillages || {};
    const preselect = window.poaPreselect || null;
    const role = window.poaUserRole || 'registrar';

    const $citizenSelect = $('#citizen_select');
    const $fullName = $('#full_name');
    const $phone = $('#phone');
    const $citizenId = $('#citizen_id');
    const $communityField = $('#community_field');
    const $villageSelect = $('#village_select');
    const $landLocation = $('#land_location');
    const $numberOfPlots = $('#number_of_plots');
    const $paymentDate = $('#payment_date');
    const $paymentAmount = $('#payment_amount');
    const $submitBtn = $('#poaSubmit');
    const $form = $('#poaForm');

    function populateVillages(commId, selectedVillage = null) {
        $villageSelect.html('<option value="">-- Select Village --</option>');
        if (!commId || !villagesByCommunity[commId]) return;
        villagesByCommunity[commId].forEach(v => {
            const opt = $('<option>').val(v.id).text(v.name);
            if (selectedVillage && v.id == selectedVillage) opt.prop('selected', true);
            $villageSelect.append(opt);
        });
    }

    function fillCitizen(opt) {
        if (!opt || !opt.length) return;
        const full = opt.data('full') || '';
        const phone = opt.data('phone') || '';
        const citizenid = opt.data('citizenid') || '';
        const house = opt.data('house') || '';
        const comm = opt.data('community') || '';
        const vill = opt.data('village') || '';

        // set fields (admin can overwrite)
        if (role === 'admin') {
            $fullName.val(full);
            $phone.val(phone);
        } else {
            $fullName.val(full).prop('readonly', true).addClass('disabled');
            $phone.val(phone).prop('readonly', true).addClass('disabled');
        }

        $citizenId.val(citizenid);
        $landLocation.val(house || '');

        // community
        if ($communityField.prop('tagName') === 'SELECT') {
            $communityField.val(comm);
            populateVillages(comm, vill);
        } else {
            // readonly input with community name - try to set village anyway (comm is id)
            populateVillages(comm, vill);
        }
    }

    // If a citizen is preselected from server side
    if (preselect) {
        const opt = $citizenSelect.find('option').filter(function () {
            return $(this).val() == preselect.id;
        });
        if (opt.length) fillCitizen(opt);
    }

    $citizenSelect.on('change', function () {
        fillCitizen($(this).find('option:selected'));
    });

    // If admin changes community select, populate villages
    if ($communityField.prop('tagName') === 'SELECT') {
        $communityField.on('change', function () {
            populateVillages($(this).val());
        });
    }

    // Form submit via AJAX with SweetAlert2
    $form.on('submit', function (e) {
        e.preventDefault();

        // Basic client-side validation
        const citizenId = $citizenSelect.val();
        const amount = $paymentAmount.val();
        const villageId = $villageSelect.val();
        const paymentDate = $paymentDate.val();
        const numPlots = $numberOfPlots.val();

        if (!citizenId) {
            Swal.fire({ icon: 'warning', title: 'Select citizen', text: 'Please choose a citizen.' });
            return;
        }
        if (!amount || parseFloat(amount) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Invalid amount', text: 'Enter a valid payment amount.' });
            return;
        }
        if (!villageId) {
            Swal.fire({ icon: 'warning', title: 'Select village', text: 'Please choose a village.' });
            return;
        }
        if (!paymentDate) {
            Swal.fire({ icon: 'warning', title: 'Select date', text: 'Please choose the payment date.' });
            return;
        }
        if (!numPlots || parseInt(numPlots) < 1) {
            Swal.fire({ icon: 'warning', title: 'Invalid plots', text: 'Enter number of plots.' });
            return;
        }

        // Build form data
        const fd = new FormData();

        fd.append('ajax', 1); // tell backend it's an ajax request
        fd.append('citizen_id', citizenId);
        fd.append('payment_amount', amount);
        fd.append('village_id', villageId);
        fd.append('land_location', $landLocation.val() || '');
        fd.append('number_of_plots', numPlots);
        fd.append('payment_date', paymentDate);

        // community name: backend expects community_name (not id). If select, use text
        if ($communityField.prop('tagName') === 'SELECT') {
            const sel = $communityField.find('option:selected');
            const commName = sel.length ? sel.text() : '';
            fd.append('community_name', commName);
            fd.append('community_id', $communityField.val());
        } else {
            fd.append('community_name', $communityField.val() || '');
        }

        // If admin edited full name or phone, include overrides (not required by current backend but harmless)
        if (role === 'admin') {
            if ($fullName.val()) fd.append('override_full_name', $fullName.val());
            if ($phone.val()) fd.append('override_phone', $phone.val());
        }

        // Disable submit while sending
        $submitBtn.prop('disabled', true).text('Issuing...');

        $.ajax({
            url: 'generate_poa.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 60000
        }).done(function (res) {
            if (!res) {
                Swal.fire({ icon: 'error', title: 'No response', text: 'Server did not return a valid response.' });
                return;
            }

            if (res.status && res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Issued',
                    html: 'POA issued successfully. Redirecting to records...',
                    timer: 2000,
                    showConfirmButton: false,
                    willClose: () => {}
                }).then(() => {
                    // Redirect to poa_records.php
                    window.location.href = 'poa_records.php';
                });

            } else {
                const msg = (res.message) ? res.message : 'Failed to issue POA';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            let msg = 'Request failed';
            if (textStatus === 'timeout') msg = 'Request timed out';
            else if (jqXHR.responseJSON && jqXHR.responseJSON.message) msg = jqXHR.responseJSON.message;
            else if (jqXHR.responseText) {
                try {
                    const j = JSON.parse(jqXHR.responseText);
                    if (j.message) msg = j.message;
                } catch (e) {}
            }
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        }).always(function () {
            $submitBtn.prop('disabled', false).text('Issue Certificate');
        });
    });

});
