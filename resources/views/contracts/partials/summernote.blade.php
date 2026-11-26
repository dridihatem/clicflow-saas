{{--
  Summernote for contract body fields.
  @var string $textareaId
  @var string|null $hintsId
  @var string|null $typeSelectId  form select for type_key (optional)
  @var string|null $defaultType
  @var int|null $height
--}}
@php
    $textareaId = $textareaId ?? 'contract_body_html';
    $hintsId = $hintsId ?? null;
    $typeSelectId = $typeSelectId ?? null;
    $defaultType = $defaultType ?? 'service';
    $height = (int) ($height ?? 420);
    $modalName = 'contract-sample-'.preg_replace('/[^a-z0-9_-]/i', '-', $textareaId);
@endphp

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js" crossorigin="anonymous"></script>
    <script>
        (function () {
            const ta = document.getElementById(@json($textareaId));
            if (!ta || typeof jQuery === 'undefined' || !jQuery.fn.summernote) {
                return;
            }

            const $ta = jQuery(ta);
            if ($ta.next('.note-editor').length) {
                return;
            }

            const isDark = document.documentElement.classList.contains('dark');

            $ta.summernote({
                height: {{ $height }},
                dialogsInBody: true,
                placeholder: {!! json_encode(__('contract_body_label')) !!},
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'hr']],
                    ['view', ['fullscreen', 'codeview', 'help']],
                ],
            });

            if (isDark) {
                $ta.next('.note-editor').find('.note-editable').css({
                    color: '#f8fafc',
                    backgroundColor: '#0f172a',
                });
            }

            function setEditorHtml(html) {
                if ($ta.next('.note-editor').length) {
                    $ta.summernote('code', html);
                } else {
                    ta.value = html;
                }
            }

            function insertTag(text) {
                if ($ta.next('.note-editor').length) {
                    $ta.summernote('focus');
                    $ta.summernote('pasteHTML', text);
                    return;
                }
                const start = ta.selectionStart ?? ta.value.length;
                const end = ta.selectionEnd ?? start;
                ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + text.length;
            }

            window['flowdeskContractEditor_' + @json($textareaId)] = { setHtml: setEditorHtml, insertTag: insertTag };

            @if ($hintsId)
                document.getElementById(@json($hintsId))?.addEventListener('click', function (e) {
                    const btn = e.target.closest('[data-tag]');
                    if (!btn) return;
                    e.preventDefault();
                    const tag = btn.getAttribute('data-tag') || '';
                    if (tag) insertTag(tag);
                });
            @endif

            const form = ta.closest('form');
            form?.addEventListener('submit', function () {
                if ($ta.next('.note-editor').length) {
                    ta.value = $ta.summernote('code');
                }
            });

            const insertBtn = document.getElementById(@json($modalName.'-insert'));
            insertBtn?.addEventListener('click', function () {
                const panel = document.getElementById(@json($modalName.'-panel'));
                const typeEl = panel?.querySelector('[name="sample_type"]');
                const localeEl = panel?.querySelector('input[name="'+@json($modalName.'_locale')+'"]:checked');
                const type = typeEl?.value || @json($defaultType);
                const locale = localeEl?.value || 'en';
                const url = @json(url('/contracts/samples')).replace(/\/$/, '') + '/' + encodeURIComponent(type) + '/' + encodeURIComponent(locale);
                const confirmMsg = {!! json_encode(__('Replace contract body with the sample text for this language?')) !!};

                if (!window.confirm(confirmMsg)) {
                    return;
                }

                fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('sample');
                        return r.json();
                    })
                    .then(function (data) {
                        if (data && data.html) {
                            setEditorHtml(data.html);
                            @if ($typeSelectId)
                                const typeSelect = document.getElementById(@json($typeSelectId));
                                if (typeSelect && data.type) {
                                    typeSelect.value = data.type;
                                }
                            @endif
                        }
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: @json($modalName) }));
                    })
                    .catch(function () {
                        alert({!! json_encode(__('Could not load sample text. Try again.')) !!});
                    });
            });
        })();
    </script>
@endpush
