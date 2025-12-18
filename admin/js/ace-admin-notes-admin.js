(function ($) {
	'use strict';

	$(function () {
		const data = window.AceAdminNotesData || {};
		const palette = data.palette || {};
		const settings = data.settings || {};
		const screenId = data.screenId || '';
		const userRoles = data.userRoles || [];
		const allowedRoles = settings.allowed_roles || [];
		const excludedScreens = settings.excluded_screens || [];

		// Canvas is injected in admin_footer; fetch on demand so we don't miss it
		const getCanvas = () => $('#ace-admin-notes-canvas');
		const $adminTable = $('.ace-notes-admin-table');
		const $adminTableBody = $adminTable.find('tbody');
		const $mainForm = $('form').filter((_, el) => ['add_note', 'edit_note'].includes($(el).find('input[name="aan_action"]').val()));
		const $modal = $('#aan-modal');
		const $modalForm = $('#aan-modal-form');

		const normalize = (note = {}) =>
			Object.assign(
				{
					id: note.id || '',
					title: '',
					content: '',
					color: 'yellow',
					active: true,
					hidden: false,
					x: 40,
					y: 120,
					width: 260,
					height: 200,
				},
				note || {}
			);

		let notes = (data.notes || []).map(normalize);
		const overlayAllowed =
			(!allowedRoles.length || allowedRoles.some((r) => userRoles.includes(r))) &&
			!excludedScreens.includes(screenId);

		const round = (val) => Math.round(Number(val) || 0);
		const getNote = (id) => notes.find((n) => n.id === id) || null;

		const ajax = (payload, onSuccess) => {
			$.post(
				data.ajaxUrl,
				Object.assign({ nonce: data.nonce }, payload),
				(response) => {
					if (response && response.success && onSuccess) {
						onSuccess(response.data);
					}
				},
				'json'
			);
		};

		const upsert = (note) => {
			const n = normalize(note);
			const i = notes.findIndex((x) => x.id === n.id);
			if (i >= 0) notes[i] = n;
			else notes.push(n);
		};

		const remove = (id) => {
			notes = notes.filter((n) => n.id !== id);
		};

		const buildCard = (note) => {
		const color = palette[note.color] || note.color || '#fff7a3';

		const card = $('<span class="ace-note-card"></span>')
		.attr('data-note-id', note.id)
		.css({
			left: note.x,
			top: note.y,
			width: note.width,
			height: note.height,
			background: color
		});

		/* ---------- Header ---------- */
		const $header = $('<div class="ace-note-card__header"></div>');
		const $title  = $('<span class="ace-note-card__title"></span>').text(note.title || 'Note');

		/* ---------- Buttons ---------- */
		const $minBtn  = $('<button type="button" class="ace-note-card__btn ace-min" aria-label="Minimize">–</button>');
		const $fullBtn = $('<button type="button" class="ace-note-card__btn ace-full" aria-label="Fullscreen">⛶</button>');
		const $hideBtn = $('<button type="button" class="ace-note-card__btn ace-hide" aria-label="Hide">×</button>');

		const $actions = $('<div class="ace-note-card__actions"></div>')
		.append($minBtn, $fullBtn, $hideBtn);

		$header.append($title, $actions);
		card.append($header);

		/* ---------- Body ---------- */
		const body = $('<div class="ace-note-card__body"></div>')
		.text(note.content || '');

		card.append(body);

		/* ---------- State ---------- */
		let isMinimized  = false;
		let isFullscreen = false;
		let prev = {};

		/* ---------- Minimize ---------- */
		$minBtn.on('click', () => {
			body.toggleClass('minimized_body');
			card.toggleClass('minimized');
			updateMinimizedStack();
		});
		function updateMinimizedStack() {
			$('.ace-note-card.minimized').each(function (index) {
			$(this).css('--stack-index', index);
		});
	}
			/* ---------- Fullscreen ---------- */
		$fullBtn.on('click', () => {
		isFullscreen = !isFullscreen;
		if (isFullscreen) {
			prev = {
				left: card.css('left'),
				top: card.css('top'),
				width: card.width(),
				height: card.height(),
				zIndex: card.css('z-index')
			};

			card.addClass('is-fullscreen').css({
				left: 0,
				top: 0,
				width: '100%',
				height: '100%',
				zIndex: 9999
			});
		} else {
			card.removeClass('is-fullscreen').css(prev);
		}
		});

		/* ---------- Hide ---------- */
		$hideBtn.on('click', () => {
			const idx = notes.findIndex(n => n.id === note.id);
			if (idx >= 0) {
				notes[idx] = { ...notes[idx], hidden: true };
			}
			refresh();
		});

		/* ---------- Drag & Resize ---------- */
		card.draggable({
			handle: '.ace-note-card__header',
			containment: '#ace-admin-notes-canvas',
			scroll: false,
			disabled: false,
			start: () => {
				card.removeClass('minimized');
				body.toggleClass('minimized_body');
			},
			stop: (_e, ui) => savePosition(note.id, ui.position, card),
		});

		card.resizable({
			minHeight: 140,
			minWidth: 160,
			handles: 'all',
			containment: '#ace-admin-notes-canvas',
		stop: (_e, ui) => saveSize(note.id, ui.size, ui.position, card),
		});

		return card;
		};


		const renderOverlay = () => {
			const $canvas = getCanvas();
			if (!$canvas.length || !overlayAllowed) return;
			$canvas.empty();
			notes.forEach((nRaw) => {
				const n = normalize(nRaw);
				if (!n.active || n.hidden) return;
				$canvas.append(buildCard(n));
			});
		};

		const fetchNotes = (done) => {
			ajax({ action: 'aan_fetch_notes' }, (resp) => {
				if (resp && resp.notes) {
					notes = resp.notes.map(normalize);
				}
				if (resp && resp.settings) {
					Object.assign(settings, resp.settings);
				}
				if (done) done();
				refresh();
			});
		};

		// CRUD wrappers.
		const addNote = (payload, done) => {
			ajax({ action: 'aan_add_note', ...payload }, (resp) => {
				if (resp && resp.note) upsert(resp.note);
				refresh();
				if (done) done();
			});
		};

		const updateNote = (id, payload, done, opts = {}) => {
			const { refreshOnComplete = true } = opts;
			ajax({ action: 'aan_update_note', 
				id, ...payload }, (resp) => {
				if (resp && resp.note) upsert(resp.note);
				if (refreshOnComplete) refresh();
				if (done) done();
			});
		};


		const savePosition = (id, position = {}, card) => {
			const note = getNote(id);
			const payload = {};

			if ('left' in position) payload.x = round(position.left);
			if ('top' in position) payload.y = round(position.top);

			// Preserve stored size; do not recalc on drag to avoid drift.
			if (note) {
				if ('width' in note) payload.width = round(note.width);
				if ('height' in note) payload.height = round(note.height);
			}

			if (Object.keys(payload).length) {
				upsert(Object.assign({}, note || {}, payload));
				updateNote(id, payload, null, { refreshOnComplete: false });
			}
		};

		const saveSize = (id, size = {}, position = {}, card) => {
			const note = getNote(id);
			const payload = {};

			if ('left' in position) payload.x = round(position.left);
			if ('top' in position) payload.y = round(position.top);
			if ('width' in size) payload.width = round(size.width);
			if ('height' in size) payload.height = round(size.height);

			if (Object.keys(payload).length) {
				upsert(Object.assign({}, note || {}, payload));
				updateNote(id, payload, null, { refreshOnComplete: false });
			}
		};

		const refresh = () => {
			renderOverlay();
		};

		// Forms: main.
		const resetMainForm = () => {
			if (!$mainForm.length) return;
			$mainForm[0].reset();
			$mainForm.find('input[name="aan_action"]').val('add_note');
			$mainForm.find('input[name="id"]').remove();
		};


		// Leave the main admin form to submit normally so the table below
		// refreshes with server-rendered data.

		// Modal form.
		if ($modalForm.length) {
			$modalForm.on('submit', function (e) {
				e.preventDefault();
				const payload = {
					title: $modalForm.find('input[name="title"]').val(),
					content: $modalForm.find('textarea[name="content"]').val(),
					color: $modalForm.find('select[name="color"]').val(),
					active: $modalForm.find('input[name="active"]').is(':checked') ? 1 : 0,
				};
				addNote(payload, () => {
					$modal.removeClass('is-open').attr('aria-hidden', 'true');
					$modalForm[0].reset();
				});
			});
		}

		// Modal toggles.
		const openModal = () => $modal.addClass('is-open').attr('aria-hidden', 'false');
		const closeModal = () => $modal.removeClass('is-open').attr('aria-hidden', 'true');
		$('.aan-open-modal').on('click', (e) => {
			e.preventDefault();
			openModal();
		});
		$('.aan-modal__close, .aan-modal__backdrop').on('click', (e) => {
			e.preventDefault();
			closeModal();
		});

		fetchNotes(refresh);
	});
})(jQuery);
