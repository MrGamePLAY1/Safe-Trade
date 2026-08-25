<?php
require_once __DIR__ . '/../src/layout.php';

$u  = require_login();
$id = (int)($_GET['listing'] ?? $_POST['listing'] ?? 0);
$l  = db_row('SELECT * FROM listings WHERE id = ?', [$id]);

if (!$l || (int)$l['user_id'] !== (int)$u['id']) {
    flash('You can only manage documents on your own listings.', 'warn');
    redirect('dashboard.php');
}

$uploadDir = __DIR__ . '/uploads';
$allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];

/* ---- handle POST: add or delete a document ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['delete_doc'])) {
        $doc = db_row('SELECT * FROM documents WHERE id = ? AND listing_id = ?', [(int)$_POST['delete_doc'], $id]);
        if ($doc) {
            if ($doc['file_path'] && file_exists($uploadDir . '/' . $doc['file_path'])) {
                unlink($uploadDir . '/' . $doc['file_path']);
            }
            db_exec('DELETE FROM documents WHERE id = ?', [$doc['id']]);
            flash('Document removed.');
        }
        redirect('documents.php?listing=' . $id);
    }

    $type  = $_POST['doc_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $note  = trim($_POST['note'] ?? '');

    $validTypes = array_merge(array_keys(CORE_DOCS), ['other']);
    if (!in_array($type, $validTypes, true)) {
        flash('Pick a document type.', 'error');
        redirect('documents.php?listing=' . $id);
    }
    if ($title === '') {
        $title = $type === 'other' ? 'Additional document' : CORE_DOCS[$type];
    }

    // Optional file upload. Basic dev-build validation: extension allowlist,
    // 5 MB cap, random stored name. TODO: content-type sniffing + virus scan for production.
    $storedName = null;
    if (!empty($_FILES['file']['name'])) {
        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($f['error'] !== UPLOAD_ERR_OK) {
            flash('Upload failed — try again.', 'error');
            redirect('documents.php?listing=' . $id);
        }
        if (!in_array($ext, $allowedExt, true)) {
            flash('Only PDF, JPG or PNG files are accepted.', 'error');
            redirect('documents.php?listing=' . $id);
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            flash('File too large — 5 MB max.', 'error');
            redirect('documents.php?listing=' . $id);
        }
        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $uploadDir . '/' . $storedName)) {
            flash('Could not save the file.', 'error');
            redirect('documents.php?listing=' . $id);
        }
    }

    db_exec(
        'INSERT INTO documents (listing_id, doc_type, title, note, file_path) VALUES (?,?,?,?,?)',
        [$id, $type, $title, $note ?: null, $storedName]
    );
    flash('Added to the dossier.');
    redirect('documents.php?listing=' . $id);
}

$docs = listing_docs($id);
$dos  = dossier($docs);

page_header('Dossier — ' . $l['make'] . ' ' . $l['model'], 'sell');
?>
<div class="wrap section-tight">
  <p class="small"><a href="listing.php?id=<?= $id ?>">← Back to listing</a></p>
  <h1>Build the dossier</h1>
  <p class="muted"><?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?> · <?= reg_plate($l['reg']) ?></p>

  <div class="detail-grid" style="margin-top:20px">
    <div>
      <div class="panel">
        <h3>Add a document</h3>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="listing" value="<?= $id ?>">
          <div class="field" style="margin-bottom:14px">
            <label for="doc_type">Category</label>
            <select id="doc_type" name="doc_type" required>
              <option value="">— Choose —</option>
              <?php foreach (CORE_DOCS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $dos['slots'][$key]['present'] ? '' : '' ?>>
                  <?= e($label) ?><?= $dos['slots'][$key]['present'] ? ' ✓ (already added)' : '' ?>
                </option>
              <?php endforeach; ?>
              <option value="other">Other (photos of receipts, extras…)</option>
            </select>
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" placeholder="e.g. NCT certificate to 07/2027">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="note">Note (optional)</label>
            <input type="text" id="note" name="note" placeholder="e.g. Passed with no advisories">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="file">File — PDF, JPG or PNG, max 5 MB (optional)</label>
            <input type="file" id="file" name="file" accept=".pdf,.jpg,.jpeg,.png">
            <p class="small muted" style="margin:6px 0 0">
              You can declare a document now and upload the scan later — buyers see the difference.
            </p>
          </div>
          <button class="btn btn-primary" type="submit">Add to dossier</button>
        </form>
      </div>

      <div class="todo-note" style="margin-top:18px">
        <strong>Roadmap:</strong> the "Verified" stamp is currently set in the database only.
        The planned flow is that a mechanic who completes an inspection can verify documents
        they've sighted — see README.
      </div>
    </div>

    <div>
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:baseline">
          <h3 style="margin:0">Dossier status</h3>
          <span class="mono small"><?= $dos['present'] ?>/<?= $dos['total'] ?> core</span>
        </div>
        <div style="margin:12px 0"><?= dossier_meter($dos, true) ?></div>

        <?php if ($docs): ?>
          <ul class="doc-list">
            <?php foreach ($docs as $d): ?>
              <li>
                <div class="doc-row">
                  <div>
                    <strong><?= e($d['title']) ?></strong>
                    <span class="small muted"> · <?= e(CORE_DOCS[$d['doc_type']] ?? 'Other') ?></span>
                    <?php if ($d['verified']): ?> <span class="stamp">Verified</span><?php endif; ?>
                    <?php if (!$d['file_path']): ?><br><span class="small muted">Declared — no file yet</span><?php endif; ?>
                  </div>
                  <form method="post" class="inline-form"
                        onsubmit="return confirm('Remove this document from the dossier?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing" value="<?= $id ?>">
                    <button class="btn btn-danger btn-sm" name="delete_doc" value="<?= $d['id'] ?>">Remove</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">Nothing attached yet. Start with the NCT cert and service history — they're the two things every buyer asks for first.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
