import Link from 'next/link'
import clsx from 'clsx'

const variantStyles = {
  primary:
    'border-2 border-ink bg-ink py-2.5 px-5 text-sm font-semibold text-white hover:bg-tangerine hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink',
  secondary:
    'border-2 border-ink bg-white py-2.5 px-5 text-sm font-semibold text-ink hover:bg-volt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink',
}

type ButtonProps = {
  variant?: keyof typeof variantStyles
} & (
  | React.ComponentPropsWithoutRef<typeof Link>
  | (React.ComponentPropsWithoutRef<'button'> & { href?: undefined })
)

export function Button({
  variant = 'primary',
  className,
  ...props
}: ButtonProps) {
  className = clsx(variantStyles[variant], className)

  return typeof props.href === 'undefined' ? (
    <button className={className} {...props} />
  ) : (
    <Link className={className} {...props} />
  )
}
