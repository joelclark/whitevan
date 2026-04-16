import AppLogoIcon from '@/components/app-logo-icon';

export default function QuoteLayout({
    accountName,
    children,
}: {
    accountName: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-h-svh bg-background">
            <header className="border-b">
                <div className="mx-auto flex max-w-4xl items-center gap-3 px-6 py-4">
                    <AppLogoIcon className="size-7 fill-current text-foreground" />
                    <span className="text-lg font-semibold">{accountName}</span>
                </div>
            </header>
            <main className="mx-auto max-w-4xl px-6 py-8">{children}</main>
        </div>
    );
}
