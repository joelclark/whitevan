import { X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState
    
} from 'react';
import type {ReactNode} from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchInputProps = {
    value: string;
    onSearch: (value: string) => void;
    placeholder?: string;
    icon?: ReactNode;
    className?: string;
    wrapperClassName?: string;
    delayMs?: number;
};

export function SearchInput({
    value,
    onSearch,
    placeholder,
    icon,
    className,
    wrapperClassName,
    delayMs = 300,
}: SearchInputProps) {
    const [localValue, setLocalValue] = useState(value);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const onSearchRef = useRef(onSearch);

    useEffect(() => {
        onSearchRef.current = onSearch;
    }, [onSearch]);

    useEffect(() => {
        setLocalValue(value);
    }, [value]);

    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    const handleChange = useCallback(
        (next: string) => {
            setLocalValue(next);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            timeoutRef.current = setTimeout(() => {
                onSearchRef.current(next);
            }, delayMs);
        },
        [delayMs],
    );

    const handleClear = useCallback(() => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }

        setLocalValue('');
        onSearchRef.current('');
    }, []);

    return (
        <div className={cn('relative', wrapperClassName)}>
            {icon}
            <Input
                placeholder={placeholder}
                value={localValue}
                onChange={(e) => handleChange(e.target.value)}
                className={cn('pr-9', className)}
            />
            {localValue.length > 0 && (
                <button
                    type="button"
                    onClick={handleClear}
                    aria-label="Clear search"
                    className="absolute top-1/2 right-2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
