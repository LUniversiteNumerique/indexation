$(document).on('change', '#Notice_champDisc, #Notice_discipline', function () {
    const $field = $(this), data = {}
    const $champField = $('#Notice_champDisc')
    const $form = $field.closest('form')
    const target = '#' + $field.attr('id')
        .replace('Notice_discipline', 'Notice_specialite')
        .replace('Notice_champDisc', 'Notice_discipline')
    data[$champField.attr('name')] = $champField.val()
    data[$field.attr('name')] = $field.val()
    $.post($form.attr('action'), data).fail(({responseText}) =>
        $(target).replaceWith($(responseText).find(target))
    )
})