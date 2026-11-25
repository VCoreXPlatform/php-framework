<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Note;
use Core\Controller;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

final class NoteController extends Controller
{
    public function __construct(private readonly Note $notes)
    {
    }

    public function index(Request $request): Response
    {
        $page = $this->integer($request->query['page'] ?? 1, 1_000_000, 422);
        $size = $this->integer($request->query['per_page'] ?? 20, 100, 422);
        return $this->json($this->notes->query()->orderBy('id', 'desc')->paginate($page, $size));
    }

    public function show(string $id): Response
    {
        return $this->json($this->record($this->notes->find($this->integer($id))));
    }

    public function create(Request $request): Response
    {
        $note = $this->notes->create($this->fields($request, false));
        return $this->json($note, 201, ['Location' => '/api/notes/' . $note['id']]);
    }

    public function update(Request $request, string $id): Response
    {
        $note = $this->notes->update($this->integer($id), $this->fields($request, $request->method === 'PATCH'));
        return $this->json($this->record($note));
    }

    public function delete(string $id): Response
    {
        if (!$this->notes->delete($this->integer($id))) {
            throw new HttpException(404, 'Note not found');
        }
        return new Response(status: 204);
    }

    private function integer(mixed $value, int $max = PHP_INT_MAX, int $status = 404): int
    {
        $checked = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
        if ($checked === false) {
            throw new HttpException($status, 'Invalid ID or pagination parameter');
        }
        return $checked;
    }

    private function record(?array $note): array
    {
        return $note ?? throw new HttpException(404, 'Note not found');
    }

    private function fields(Request $request, bool $partial): array
    {
        $input = $request->input();
        if (!$partial) {
            $input += ['body' => ''];
        }
        $data = [];
        foreach (['title' => 160, 'body' => 8000] as $field => $max) {
            if ($partial && !array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field] ?? null;
            if (
                !is_string($value)
                || preg_match('//u', $value) !== 1
                || mb_strlen($value, 'UTF-8') > $max
                || ($field === 'title' && trim($value) === '')
            ) {
                throw new HttpException(422, "Invalid {$field}");
            }
            $data[$field] = trim($value);
        }
        if ($data === []) {
            throw new HttpException(422, 'Expected title or body');
        }
        return $data;
    }
}
