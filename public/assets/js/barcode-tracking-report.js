$(document).ready(function () {
  $('.select2').select2({
    theme: 'bootstrap-5',
    allowClear: true,
    width: '100%'
  })

  $('.flatpickr-date').flatpickr({
    dateFormat: 'd/m/Y',
    allowInput: true
  })
})
