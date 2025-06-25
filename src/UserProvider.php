<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Types\Str;

use function call_user_func;

/**
 * @internal
 */
final class UserProvider
{
    // TODO we need to reset this state between executions.
    private ?Authenticatable $rememberedUser = null;

    /**
     * @var (callable(): (null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})))
     */
    public $userDetailsResolverResolver;

    public function __construct(
        private AuthManager $auth,
        callable $userDetailsResolverResolver,
    ) {
        $this->userDetailsResolverResolver = $userDetailsResolverResolver;
    }

    /**
     * @return string|LazyValue<string>
     */
    public function id(): LazyValue|string
    {
        if ($this->auth->hasUser()) {
            $id = $this->getId();

            return Str::tinyText((string) $id); // @phpstan-ignore cast.string
        }

        return new LazyValue(function () {
            $id = $this->getId();

            return Str::tinyText((string) $id); // @phpstan-ignore cast.string
        });
    }

    /**
     * @return array{ id: mixed, name?: mixed, username?: mixed }|null
     */
    public function details(): ?array
    {
        $user = $this->auth->user() ?? $this->rememberedUser;

        if ($user === null) {
            return null;
        }

        $resolver = call_user_func($this->userDetailsResolverResolver);

        if ($resolver === null) {
            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? '',
                'username' => $user->email ?? '',
            ];
        }

        return [
            'id' => $user->getAuthIdentifier(),
            ...$resolver($user),
        ];
    }

    public function remember(Authenticatable $user): void
    {
        $this->rememberedUser = $user;
    }

    private function getId(): int|string|null
    {
        $id = $this->auth->id();
        $details = $this->details();
        if (isset($details['id'])) {
            $id = $details['id'];
        }

        return $id;
    }
}
