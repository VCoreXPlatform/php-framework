import { useEffect, useState, type FormEvent } from 'react';
import { createRoot } from 'react-dom/client';
import { api } from './api';
import './style.css';

type Note = { id: number | string; title: string; body: string };
type Page = { data: Note[]; total: number; per_page: number };
const emptyDraft = { title: '', body: '' };

function App() {
  const [health, setHealth] = useState('Проверка PHP API…');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<Page>();
  const [draft, setDraft] = useState(emptyDraft);
  const [editing, setEditing] = useState<Note['id']>();
  const [revision, setRevision] = useState(0);
  const [loadError, setLoadError] = useState('');
  const [actionError, setActionError] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const request = new AbortController();
    api<{ status: string }>('/health', 'GET', undefined, request.signal)
      .then(() => setHealth('PHP API доступен'))
      .catch(() => { if (!request.signal.aborted) setHealth('PHP API недоступен'); });
    return () => request.abort();
  }, [revision]);

  useEffect(() => {
    const request = new AbortController();
    setLoading(true);
    setLoadError('');
    api<Page>('/notes?page=' + page + '&per_page=10', 'GET', undefined, request.signal)
      .then((next) => {
        const last = Math.max(1, Math.ceil(next.total / next.per_page));
        if (page > last) setPage(last);
        else setResult(next);
      })
      .catch((error: Error) => { if (!request.signal.aborted) setLoadError(error.message); })
      .finally(() => { if (!request.signal.aborted) setLoading(false); });
    return () => request.abort();
  }, [page, revision]);

  function reset() {
    setEditing(undefined);
    setDraft(emptyDraft);
  }

  async function mutate(operation: () => Promise<unknown>, onSuccess: () => void) {
    setBusy(true);
    setActionError('');
    try {
      await operation();
      onSuccess();
      setRevision((value) => value + 1);
    } catch (error) {
      setActionError(error instanceof Error ? error.message : String(error));
    } finally {
      setBusy(false);
    }
  }

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const current = editing;
    void mutate(
      () => api(current === undefined ? '/notes' : '/notes/' + current, current === undefined ? 'POST' : 'PATCH', draft),
      () => { reset(); if (current === undefined) setPage(1); },
    );
  }

  return (
    <main>
      <header>
        <div>
          <p className="brand">PHP Framework</p>
          <h1>Заметки</h1>
        </div>
        <p className="status" role="status">{health}</p>
      </header>
      <div className="workspace">
        <section className="panel" aria-labelledby="form-title">
          <h2 id="form-title">{editing === undefined ? 'Новая заметка' : 'Редактирование заметки'}</h2>
          <form onSubmit={save}>
            <label>Заголовок
              <input required maxLength={160} value={draft.title} disabled={busy} onChange={(event) => setDraft({ ...draft, title: event.target.value })} />
            </label>
            <label>Текст
              <textarea maxLength={8000} rows={6} value={draft.body} disabled={busy} onChange={(event) => setDraft({ ...draft, body: event.target.value })} />
            </label>
            <div className="actions">
              <button disabled={busy} className="primary">{busy ? 'Подождите…' : 'Сохранить'}</button>
              {editing !== undefined && <button type="button" disabled={busy} onClick={reset}>Отмена</button>}
            </div>
          </form>
          {actionError && <p role="alert">{actionError}</p>}
        </section>
        <section className="panel" aria-labelledby="list-title">
          <div className="list-heading">
            <h2 id="list-title">Все заметки{result ? ' · ' + result.total : ''}</h2>
            <button disabled={loading || busy} onClick={() => setRevision((value) => value + 1)}>Обновить</button>
          </div>
          {loading ? <p role="status">Загрузка…</p> : loadError ? <p role="alert">Ошибка загрузки: {loadError}</p> :
            result?.data.length === 0 ? <p>Заметок пока нет.</p> : (
              <ul>
                {result?.data.map((note) => (
                  <li key={note.id}>
                    <h3>{note.title}</h3>
                    {note.body && <p className="body">{note.body}</p>}
                    <div className="actions">
                      <button disabled={busy} onClick={() => {
                        setEditing(note.id);
                        setDraft({ title: note.title, body: note.body });
                        document.querySelector('input')?.focus();
                      }}>Изменить</button>
                      <button disabled={busy} onClick={() => {
                        if (window.confirm('Удалить заметку?')) {
                          void mutate(() => api('/notes/' + note.id, 'DELETE'), () => { if (editing === note.id) reset(); });
                        }
                      }}>Удалить</button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          {result && result.total > result.per_page && !loadError && (
            <nav className="actions" aria-label="Страницы списка">
              <button disabled={loading || busy || page === 1} onClick={() => setPage(page - 1)}>Назад</button>
              <span>{page}</span>
              <button disabled={loading || busy || page * result.per_page >= result.total} onClick={() => setPage(page + 1)}>Далее</button>
            </nav>
          )}
        </section>
      </div>
    </main>
  );
}

createRoot(document.getElementById('root')!).render(<App />);
