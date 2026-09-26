import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type TwoFactor = {
    available: boolean;
    enabled: boolean;
    blockedReason: string | null;
};

/**
 * Verificação em duas etapas do login: o estado e o botão. Ligar e desligar
 * são AÇÕES SENSÍVEIS, com a mesma confirmação do starter Livewire: senha de
 * transação → código por e-mail → a operação. O token de ação sensível nasce
 * e morre no servidor (TwoFactorPreferenceController); o navegador só manda
 * a senha e o código.
 */
export function TwoFactorCard({ twoFactor }: { twoFactor: TwoFactor }) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { auth, errors } = usePage().props;

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState<'password' | 'code'>('password');
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!twoFactor.available) {
        return null;
    }

    const close = () => {
        setOpen(false);
        setStep('password');
        setPassword('');
        setCode('');
    };

    const sendCode = () => {
        router.post(
            route('panel.two-factor.code'),
            { transaction_password: password },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    if (Object.keys(page.props.errors ?? {}).length === 0) {
                        setStep('code');
                        setCode('');
                    }
                },
            },
        );
    };

    const confirm = () => {
        router.put(
            route('panel.two-factor.update'),
            { code, enabled: !twoFactor.enabled },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    if (Object.keys(page.props.errors ?? {}).length === 0) {
                        close();
                    }
                },
            },
        );
    };

    return (
        <SectionCard
            id="two-factor"
            title={t('panel.profile.two_factor_heading')}
            description={t('panel.profile.two_factor_hint', {
                email: auth.user?.email ?? '',
            })}
        >
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Badge
                        variant={twoFactor.enabled ? 'default' : 'secondary'}
                        data-two-factor-state
                    >
                        {twoFactor.enabled
                            ? t('panel.profile.two_factor_on')
                            : t('panel.profile.two_factor_off')}
                    </Badge>
                    <Button
                        type="button"
                        variant={twoFactor.enabled ? 'outline' : 'default'}
                        disabled={twoFactor.blockedReason !== null}
                        onClick={() => setOpen(true)}
                        data-test="two-factor-toggle"
                    >
                        {twoFactor.enabled
                            ? t('panel.profile.two_factor_disable')
                            : t('panel.profile.two_factor_enable')}
                    </Button>
                </div>
                {twoFactor.blockedReason && (
                    <p
                        className="text-sm text-muted-foreground"
                        data-two-factor-blocked
                    >
                        {twoFactor.blockedReason}
                    </p>
                )}
                <InputError message={errors.two_factor} />
                <p className="text-xs text-muted-foreground">
                    {t('panel.profile.two_factor_recovery')}
                </p>
            </div>

            <Dialog
                open={open}
                onOpenChange={(value) => (value ? setOpen(true) : close())}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('panel.sensitive.heading')}
                        </DialogTitle>
                        <DialogDescription>
                            {twoFactor.enabled
                                ? t('panel.profile.two_factor_confirm_disable')
                                : t('panel.profile.two_factor_confirm_enable')}
                        </DialogDescription>
                    </DialogHeader>

                    {step === 'password' ? (
                        <form
                            className="grid gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                sendCode();
                            }}
                        >
                            <p className="text-sm text-muted-foreground">
                                {t('panel.sensitive.password_hint')}
                            </p>
                            <Label htmlFor="sensitive_transaction_password">
                                {t('auth.ui.transaction_password_title')}
                            </Label>
                            <PasswordInput
                                id="sensitive_transaction_password"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                                autoComplete="off"
                                autoFocus
                            />
                            <InputError
                                message={
                                    errors.transaction_password ??
                                    errors.two_factor
                                }
                            />
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={close}
                                >
                                    {t('panel.common.cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('panel.sensitive.send_code')}
                                </Button>
                            </DialogFooter>
                        </form>
                    ) : (
                        <form
                            className="grid gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                confirm();
                            }}
                        >
                            <p className="text-sm text-muted-foreground">
                                {t('panel.sensitive.code_hint')}
                            </p>
                            <Label htmlFor="sensitive_code">
                                {t('panel.sensitive.code')}
                            </Label>
                            <Input
                                id="sensitive_code"
                                value={code}
                                onChange={(event) =>
                                    setCode(event.target.value)
                                }
                                inputMode="numeric"
                                pattern="[0-9]*"
                                maxLength={6}
                                autoComplete="one-time-code"
                                autoFocus
                            />
                            <InputError
                                message={
                                    errors.code ?? errors.transaction_password
                                }
                            />
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={sendCode}
                                    disabled={processing}
                                >
                                    {t('auth.two_factor.resend')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('panel.sensitive.confirm')}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </SectionCard>
    );
}
