# Backend Changelog - API Estante Virtual

Todas as alterações notáveis no backend do projeto estão documentadas neste arquivo.

---

## [1.1.0] - 2026-10-31

### 🚀 Novas Funcionalidades (Cineclube & Avaliação de Filmes)

- **Banco de Dados (Opção A - Tabelas Dedicadas):**
  - Migration `create_movies_table`: armazena `title`, `director`, `year`, `runtime_minutes`, `genre`, `synopsis`, `poster_path`, `tmdb_id`, `status` (`want_to_watch`, `watching`, `watched`), `watched_at`, timestamps e soft deletes com chaves primárias ULID e isolamento por `user_id`.
  - Migration `create_movie_reviews_table`: resenhas privadas com notas de 0.5 a 5.0, título opcional, texto da resenha e flag de spoiler (`has_spoiler`). Chave única composta `[user_id, movie_id]`.
  - Migration `create_movie_tag_table`: tabela pivô reaproveitando o sistema de tags pessoais do usuário com filmes.

- **Models & Policies:**
  - Adicionado model `App\Models\Movie` com traits `BelongsToUser`, `HasUlids`, `SoftDeletes` e relações `review()`, `tags()`.
  - Adicionado model `App\Models\MovieReview` com trait `BelongsToUser` e relação `movie()`.
  - Atualizados models `App\Models\Tag` e `App\Models\User` com as relações `movies()` e `movieReviews()`.
  - Criadas as policies de autorização `App\Policies\MoviePolicy` e `App\Policies\MovieReviewPolicy`.

- **Endpoints da API (`routes/api.php`):**
  - `GET /api/v1/movies`: listagem paginada com filtros por `status`, busca (`q`), `tag`, `rating` e ordenação (`title_asc`, `title_desc`, `director`, `runtime`, `year`, `recent`).
  - `POST /api/v1/movies`: criação de filme com suporte a upload multipart (`poster`), pôster externo ou cadastro manual.
  - `GET /api/v1/movies/{movie}`: exibição de detalhes, tags e resenha do filme.
  - `PUT /api/v1/movies/{movie}`: atualização de dados e pôster.
  - `DELETE /api/v1/movies/{movie}`: exclusão lógica (soft delete).
  - `PATCH /api/v1/movies/{movie}/status`: alteração rápida de status e registro automático da data assistida.
  - `POST /api/v1/movies/{movie}/poster`: upload de arquivo de imagem privado (PNG/JPG até 5 MB).
  - `GET /api/v1/movies/{movie}/poster`: stream seguro da imagem autenticada do disco local.
  - `DELETE /api/v1/movies/{movie}/poster`: remoção do pôster do storage privado.
  - `GET /api/v1/movies/{movie}/review`: busca da resenha do filme.
  - `POST /api/v1/movies/{movie}/review` & `PUT`: criação ou atualização (upsert) da resenha e nota.
  - `DELETE /api/v1/movies/{movie}/review`: remoção da resenha.
  - `GET /api/v1/movie-reviews`: listagem global de resenhas de filmes do usuário autenticado.
  - `GET /api/v1/lookup/movies`: busca externa de metadados de filmes via query param `q`.

- **Integração Externa (TMDB API):**
  - Implementado `App\Services\MovieLookupService` consumindo a API v3 do TMDB com tradução para português (`pt-BR`), mapeamento automático de gêneros e cache de 1 hora (`Cache::remember`).
  - Fallback automático e resiliente: se a chave `TMDB_API_KEY` não for informada ou a requisição falhar, retorna lista vazia sem quebrar o fluxo, permitindo cadastro manual.
  - Configurações registradas em `config/services.php`, `.env.example` e `.env`.

- **Dashboard & Estatísticas:**
  - `App\Services\StatsService` atualizado para computar: `total_movies`, `movies_watched_this_year`, `movies_average_rating`, `movies_reviews_count`, `movie_status_counts` e `movie_ratings_distribution`.
  - Todas as chaves legadas de livros foram preservadas para retrocompatibilidade.

- **Conformidade LGPD:**
  - `AuthController::deleteAccount` atualizado para remover recursivamente o diretório de pôsteres privados do usuário (`private/posters/{user_id}`).

---

### 🔧 Manutenções & Upgrades

- **Upgrade do Framework:**
  - Atualizado para **Laravel 13.39.0** (PHP 8.3 compatível).
  - Atualizado **PHPUnit** para a versão 12.5.42.
  - Atualizado **Laravel Sanctum** para v4.3.3.
  - Atualizado **Laravel Tinker** para v3.0.2.
  - Atualizado **Collision** para v8.6.

- **Correção de Cache e URLs de Imagens:**
  - `BookResource` e `MovieResource`: URLs de capas e pôsteres agora retornam caminhos relativos acompanhados de parâmetro de versão `?v=<timestamp>` para invalidação correta de cache do navegador após substituição.

---

### 🧪 Testes Automatizados

- Criada suíte completa em `tests/Feature/MovieApiTest.php` (8 testes cobrindo CRUD, filtros, upload de pôster e validações de tamanho/formato).
- Criada suíte em `tests/Feature/MovieReviewApiTest.php` (3 testes cobrindo criação, upsert, exclusão e listagem).
- Criada suíte em `tests/Feature/MovieLookupServiceTest.php` (4 testes com `Http::fake` cobrindo sucesso, falhas de API, cache e ausência de chave).
- Estendido `tests/Feature/Isolation/DataIsolationTest.php` com **9 novos testes de isolamento estrito** garantindo retorno 404 para recursos entre usuários, proteção contra injeção de `user_id` e isolamento de estatísticas.
- **Total:** 49 testes passando (163 asserções).
