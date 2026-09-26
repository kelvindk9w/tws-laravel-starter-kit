import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

/**
 * Avatar da pessoa: a foto (só com o pacote de uploads) ou as iniciais.
 */
export function UserAvatar({
    user,
    className,
}: {
    user: User;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn('h-8 w-8 overflow-hidden rounded-full', className)}
        >
            {user.avatarUrl && (
                <AvatarImage src={user.avatarUrl} alt={user.name} />
            )}
            <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                {getInitials(user.name)}
            </AvatarFallback>
        </Avatar>
    );
}

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    return (
        <>
            <UserAvatar user={user} />
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
