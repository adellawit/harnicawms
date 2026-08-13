(function (window, $) {
  'use strict'

  var pending = null

  function getConfig() {
    return window.PosBarcodeScanConfig || {}
  }

  function isPartnerCustomer() {
    var role = String(
      $('#posWrapper').attr('data-partner-role') ||
        $('#customerSelect option:selected').attr('data-partner-role') ||
        ''
    ).toLowerCase()
    return role === 'agent' || role === 'reseller'
  }

  function syncPartnerRoleFromCustomer() {
    var role = String(
      $('#customerSelect option:selected').attr('data-partner-role') || ''
    ).toLowerCase()
    $('#posWrapper').attr('data-partner-role', role)
    return role
  }

  function setPending(product) {
    pending = product || null
    var $hint = $('#posSerialPendingHint')
    if (!pending) {
      $hint.hide().text('')
      return
    }
    $hint
      .text('Scan / ketik serial untuk: ' + (pending.name || 'product'))
      .show()
    $('#posSerialScanInput').trigger('focus')
  }

  function clearPending() {
    setPending(null)
  }

  function requestLookup(serialNumber) {
    var config = getConfig()
    var csrfToken = $('meta[name="csrf-token"]').attr('content')

    return $.ajax({
      url: config.routes && config.routes.lookup,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json'
      },
      contentType: 'application/json',
      data: JSON.stringify({
        serial_number: serialNumber,
        price_list_id:
          $('#priceListWrapper').attr('data-selected-id') ||
          $('#priceListSelect').val() ||
          '',
        pending_product_id: pending && pending.productId ? pending.productId : null,
        pending_variant_id: pending && pending.variantId ? pending.variantId : null,
        pending_unit_id: pending && pending.unitId ? pending.unitId : null
      })
    })
  }

  function alreadyInCart(serialNumber) {
    var found = false
    $('#cartItems .pos-cart-item')
      .not('#sampleCartItem')
      .each(function () {
        if (String($(this).data('serial-number') || '') === String(serialNumber)) {
          found = true
          return false
        }
      })
    return found
  }

  function ensurePriceListSelected() {
    var priceListId =
      $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || ''
    if (priceListId) return true

    Swal.fire({
      icon: 'warning',
      text: 'Pilih Type Transaction (Price List) terlebih dahulu',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    })
    return false
  }

  function ensurePartnerCustomerSelected() {
    syncPartnerRoleFromCustomer()
    if (isPartnerCustomer()) return true

    Swal.fire({
      icon: 'warning',
      title: 'Agent / Reseller belum dipilih',
      text: 'Pilih Agent atau Reseller terlebih dahulu sebelum menambah item ke keranjang.',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    }).then(function () {
      var $select = $('#customerSelect')
      if ($select.length && $select.data('select2')) {
        $select.select2('open')
      } else if ($select.length) {
        $select.trigger('focus')
      }
    })
    return false
  }

  function applyLookupSuccess(data) {
    var config = getConfig()
    if (typeof config.onScanSuccess === 'function') {
      config.onScanSuccess(data)
    }
    clearPending()
    $('#posSerialScanInput').val('').trigger('focus')
  }

  function lookupSerial(serialNumber, options) {
    options = options || {}

    if (!ensurePartnerCustomerSelected()) {
      return $.Deferred().reject().promise()
    }

    if (!ensurePriceListSelected()) {
      return $.Deferred().reject().promise()
    }

    serialNumber = $.trim(String(serialNumber || ''))
    if (!serialNumber) {
      return $.Deferred().reject({ message: 'Nomor serial barcode wajib diisi.' }).promise()
    }

    if (alreadyInCart(serialNumber)) {
      var duplicateMessage = 'Barcode ini sudah ada di keranjang'
      if (!options.silentDuplicate) {
        Swal.fire({
          icon: 'warning',
          text: duplicateMessage,
          customClass: { confirmButton: 'btn btn-primary' },
          buttonsStyling: false
        })
      }
      return $.Deferred().reject({ message: duplicateMessage }).promise()
    }

    return requestLookup(serialNumber).then(
      function (res) {
        if (!res.success || !res.data) {
          return $.Deferred()
            .reject({
              message: (res && res.message) || 'Barcode tidak valid'
            })
            .promise()
        }
        applyLookupSuccess(res.data)
        return res.data
      },
      function (xhr) {
        return $.Deferred()
          .reject({
            message:
              (xhr.responseJSON && xhr.responseJSON.message) || 'Scan barcode gagal'
          })
          .promise()
      }
    )
  }

  function invalidBarcodeAlert(err, onClose) {
    var serverMessage = String((err && err.message) || '')
    var isNotFoundOrInvalid =
      !serverMessage ||
      /tidak ditemukan|tidak valid|Barcode tidak valid/i.test(serverMessage)
    var text = isNotFoundOrInvalid
      ? 'Barcode tidak ditemukan dan tidak valid.'
      : serverMessage

    return Swal.fire({
      icon: 'error',
      title: 'Barcode tidak valid',
      text: text,
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    }).then(function () {
      if (typeof onClose === 'function') onClose()
    })
  }

  function handleScanSubmit(e) {
    if (e) e.preventDefault()

    var serial = $.trim($('#posSerialScanInput').val())
    if (!serial) return

    $('#posSerialScanSubmit').prop('disabled', true)
    lookupSerial(serial)
      .fail(function (err) {
        if (err && err.message && err.message.indexOf('sudah ada di keranjang') !== -1) {
          $('#posSerialScanInput').val('').trigger('focus')
          return
        }
        invalidBarcodeAlert(err, function () {
          $('#posSerialScanInput').trigger('focus').select()
        })
      })
      .always(function () {
        $('#posSerialScanSubmit').prop('disabled', false)
      })
  }

  function promptForProduct(product) {
    if (!ensurePartnerCustomerSelected()) {
      return
    }
    setPending(product)

    Swal.fire({
      icon: 'info',
      title: 'Scan / input barcode serial',
      html:
        '<p class="mb-2">Product <strong>' +
        $('<div>').text(product && product.name ? product.name : 'ini').html() +
        '</strong> memakai label serial.</p>' +
        '<p class="text-muted small mb-3">Scan dengan barcode gun, atau ketik nomor serial manual di bawah.</p>',
      input: 'text',
      inputPlaceholder: 'Masukkan nomor serial barcode',
      inputAttributes: {
        maxlength: 20,
        autocomplete: 'off',
        autocapitalize: 'off',
        spellcheck: 'false',
        class: 'form-control font-monospace',
        id: 'posSwalSerialInput'
      },
      showCloseButton: true,
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'Tambah ke cart',
      denyButtonText: 'Tutup',
      cancelButtonText: 'Scan di header',
      reverseButtons: true,
      focusConfirm: false,
      allowOutsideClick: false,
      allowEscapeKey: true,
      customClass: {
        confirmButton: 'btn btn-primary',
        denyButton: 'btn btn-outline-secondary',
        cancelButton: 'btn btn-label-secondary',
        input: 'form-control font-monospace',
        closeButton: 'btn btn-icon btn-sm'
      },
      buttonsStyling: false,
      didOpen: function () {
        var input = Swal.getInput()
        if (input) {
          input.focus()
          input.select()
        }
      },
      preConfirm: function (value) {
        var serial = $.trim(String(value || ''))
        if (!serial) {
          Swal.showValidationMessage('Isi nomor serial, atau pilih Scan di header')
          return false
        }
        return serial
      }
    }).then(function (result) {
      if (result.isConfirmed) {
        lookupSerial(result.value, { silentDuplicate: true }).fail(function (err) {
          invalidBarcodeAlert(err, function () {
            promptForProduct(product)
          })
        })
        return
      }

      // "Scan di header" — keep pending product, focus header scanner
      if (result.dismiss === Swal.DismissReason.cancel) {
        $('#posSerialScanInput').trigger('focus')
        return
      }

      // Close (X / Tutup / Esc) — cancel serial flow
      clearPending()
    })
  }

  $(document).on('submit', '#posSerialScanForm', handleScanSubmit)
  $(document).on('change select2:select select2:clear', '#customerSelect', syncPartnerRoleFromCustomer)
  $(function () {
    syncPartnerRoleFromCustomer()
  })

  window.PosBarcodeScan = {
    isPartnerCustomer: isPartnerCustomer,
    syncPartnerRoleFromCustomer: syncPartnerRoleFromCustomer,
    ensurePartnerCustomerSelected: ensurePartnerCustomerSelected,
    setPending: setPending,
    clearPending: clearPending,
    lookupSerial: lookupSerial,
    promptForProduct: promptForProduct
  }
})(window, jQuery)
