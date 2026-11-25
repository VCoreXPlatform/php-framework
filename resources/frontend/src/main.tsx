import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api } from './api';
import './style.css';

const statusText = {
  checking: 'Проверяем подключение…',
  online: 'PHP API работает',
  offline: 'PHP API недоступен',
};

function App() {
  const [status, setStatus] = useState<keyof typeof statusText>('checking');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const request = new AbortController();

    api<{ status: string }>('/health', 'GET', undefined, request.signal)
      .then((result) => {
        if (!request.signal.aborted) {
          setStatus(result.status === 'ok' ? 'online' : 'offline');
        }
      })
      .catch(() => {
        if (!request.signal.aborted) setStatus('offline');
      });

    return () => request.abort();
  }, [attempt]);

  return (
    <main>
      <header>
        <a className="brand" href="/">VCoreX Framework</a>
        <a href="#start">Быстрый старт</a>
      </header>
      <section className="hero" aria-labelledby="title">
        <h1 id="title">Начни с чистого проекта</h1>
        <p>PHP-ядро и React уже связаны. Проверь соединение с сервером и добавь свой первый маршрут.</p>
        <a className="button" href="https://github.com/VCoreXPlatform/php-framework#readme">Документация проекта</a>
      </section>
      <section className="panel" aria-labelledby="check-title">
        <div>
          <h2 id="check-title">Проверка сервера</h2>
          <p className="status" role="status" data-state={status}>{statusText[status]}</p>
          <a href="/api/health"><code>GET /api/health</code></a>
        </div>
        <button disabled={status === 'checking'} onClick={() => {
          setStatus('checking');
          setAttempt((previous) => previous + 1);
        }}>Проверить снова</button>
      </section>
      <section className="start" id="start" aria-labelledby="start-title">
        <h2 id="start-title">Быстрый старт</h2>
        <pre><code>{'composer create-project vcorexplatform/framework new_project\ncd new_project\nnpm ci\nnpm run build\nphp bin/framework serve'}</code></pre>
        <p>Открой <code>http://127.0.0.1:18080/</code>. Для стартовой страницы база данных не требуется.</p>
      </section>
    </main>
  );
}

createRoot(document.getElementById('root')!).render(<App />);
