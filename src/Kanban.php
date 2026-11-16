<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpError;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class Kanban
{
    public function __construct(private readonly Connection $database) {}

    public function boards(User $user): Collection
    {
        return $this->ownedBoards($user)->orderBy('id')->get();
    }

    public function createBoard(User $user, string $title): Board
    {
        return Board::query()->create(['user_id' => $user->id, 'title' => $title]);
    }

    public function editBoard(User $user, int $id, string $title): Board
    {
        return $this->updateBoard($user, $id, static function (Board $board) use ($title): Board {
            $board->update(['title' => $title]);

            return $board;
        });
    }

    public function deleteBoard(User $user, int $id): void
    {
        $this->updateBoard($user, $id, static fn (Board $board) => $board->delete());
    }

    public function columns(User $user, int $boardId): Collection
    {
        return $this->board($user, $boardId)->columns()->orderBy('sort_index')->orderBy('id')->get();
    }

    public function createColumn(User $user, int $boardId, string $title): BoardColumn
    {
        return $this->updateBoard($user, $boardId, static fn (Board $board): BoardColumn => $board->columns()->create([
            'title' => $title,
            'sort_index' => (int) $board->columns()->max('sort_index') + 1,
        ]));
    }

    public function editColumn(User $user, int $id, string $title): BoardColumn
    {
        return $this->updateColumn($user, $id, static function (BoardColumn $column) use ($title): BoardColumn {
            $column->update(['title' => $title]);

            return $column;
        });
    }

    public function deleteColumn(User $user, int $id): void
    {
        $this->updateColumn($user, $id, static fn (BoardColumn $column) => $column->delete());
    }

    public function cards(User $user, int $boardId): Collection
    {
        $board = $this->board($user, $boardId);

        return Card::query()
            ->join('board_columns', 'board_columns.id', '=', 'cards.column_id')
            ->where('board_columns.board_id', $board->id)
            ->orderBy('board_columns.sort_index')->orderBy('cards.sort_index')->orderBy('cards.id')
            ->get(['cards.*']);
    }

    public function createCard(User $user, int $columnId, string $title, string $description): Card
    {
        return $this->updateColumn($user, $columnId, fn (BoardColumn $column): Card => $column->cards()->create([
            'title' => $title,
            'description' => $description,
            'sort_index' => $this->nextCardPosition($column),
        ]));
    }

    public function editCard(User $user, int $id, string $title, ?string $description): Card
    {
        return $this->updateCard($user, $id, static function (Card $card) use ($title, $description): Card {
            $card->title = $title;
            $card->description = $description ?? $card->description;
            $card->save();

            return $card;
        });
    }

    public function deleteCard(User $user, int $id): void
    {
        $this->updateCard($user, $id, static fn (Card $card) => $card->delete());
    }

    public function archiveCard(User $user, int $id): Card
    {
        return $this->updateCard($user, $id, static function (Card $card): Card {
            if ($card->archived_at === null) {
                $card->archived_at = new DateTimeImmutable();
                $card->save();
            }

            return $card;
        });
    }

    public function moveCard(User $user, int $id, string $axis, int $direction): Card
    {
        return $this->updateCard($user, $id, function (Card $card, Board $board) use ($axis, $direction): Card {
            if ($card->archived_at !== null) {
                throw new HttpError(409, 'Архивную карточку нельзя переместить.');
            }

            $column = $board->columns()->findOrFail($card->column_id);
            $operator = $direction === 1 ? '>' : '<';
            $order = $direction === 1 ? 'asc' : 'desc';
            if ($axis === 'x') {
                $neighbor = $board->columns()->where('sort_index', $operator, $column->sort_index)
                    ->orderBy('sort_index', $order)->first();
                if ($neighbor === null) {
                    throw new HttpError(409, 'Достигнута граница доски.');
                }
                $card->column_id = $neighbor->id;
                $card->sort_index = $this->nextCardPosition($neighbor);
            } else {
                $neighbor = $column->cards()->whereNull('archived_at')->where('sort_index', $operator, $card->sort_index)
                    ->orderBy('sort_index', $order)->first();
                if ($neighbor === null) {
                    throw new HttpError(409, 'Достигнута граница колонки.');
                }
                $position = $card->sort_index;
                $card->sort_index = $neighbor->sort_index;
                $neighbor->update(['sort_index' => $position]);
            }
            $card->save();

            return $card;
        });
    }

    private function ownedBoards(User $user): Builder
    {
        return Board::query()->where('user_id', $user->id);
    }

    private function board(User $user, int $id): Board
    {
        return $this->ownedBoards($user)->findOrFail($id);
    }

    private function updateBoard(User $user, int $id, Closure $action): mixed
    {
        return $this->database->transaction(fn () => $action($this->ownedBoards($user)->lockForUpdate()->findOrFail($id)));
    }

    private function updateColumn(User $user, int $id, Closure $action): mixed
    {
        $column = BoardColumn::query()->whereIn('board_id', $this->ownedBoards($user)->select('id'))->findOrFail($id);

        return $this->updateBoard($user, $column->board_id, static fn (Board $board) => $action(
            $board->columns()->findOrFail($id),
        ));
    }

    private function updateCard(User $user, int $id, Closure $action): mixed
    {
        $card = Card::query()->join('board_columns', 'board_columns.id', '=', 'cards.column_id')
            ->whereIn('board_columns.board_id', $this->ownedBoards($user)->select('id'))
            ->where('cards.id', $id)
            ->firstOrFail(['board_columns.board_id']);

        return $this->updateBoard($user, $card->board_id, static fn (Board $board) => $action(
            Card::query()->whereIn('column_id', $board->columns()->getQuery()->select('id'))->findOrFail($id),
            $board,
        ));
    }

    private function nextCardPosition(BoardColumn $column): int
    {
        return (int) $column->cards()->max('sort_index') + 1;
    }
}
